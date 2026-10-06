<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Integration\Webhooks;

use ControlAir\Intacct\Resources\Projects\UpdateProject;
use ControlAir\Intacct\Tests\Integration\LiveTestCase;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\Webhooks\EventQueue\QueuedEvent;
use PHPUnit\Framework\Attributes\Group;

/**
 * End-to-end check of an "Event queue" Platform Trigger on the Job object.
 *
 * Setup, in a sandbox company (Platform Services > Objects > Job > New trigger):
 *   - This trigger is deployed: checked
 *   - Trigger activation:       After create, After update, After delete
 *   - Type:                     Event queue
 *   - Client ID:                the SAGE_INTACCT_CLIENT_ID value from .env
 *   - Document template:        any; its output becomes each event's payload
 *
 * Run:  composer test:webhooks
 *
 * The steps run in order and print what they find:
 *   1. The queue answers for this OAuth application.                read-only
 *   2. Editing a job puts a new event on the queue.                  opt-in: SAGE_INTACCT_TRIGGER_TEST_JOB_KEY
 *   3. Queued events are read and mapped, without removing them.     read-only
 *   4. Acknowledging the first batch removes it from the queue.     opt-in: SAGE_INTACCT_TRIGGER_TEST_ACKNOWLEDGE=true
 *
 * Step 2 edits the job's description and then restores it, which queues two events.
 * Step 4 permanently removes the events it acknowledges.
 */
#[Group('integration')]
#[Group('webhooks')]
final class LiveJobTriggerTest extends LiveTestCase
{
    private const WAIT_SECONDS = 90;

    private const POLL_SECONDS = 5;

    private const PAYLOAD_PREVIEW = 300;

    public function test_1_the_event_queue_answers_for_this_application(): void
    {
        $count = $this->client()->eventQueue->count();

        $this->report();
        $this->report(sprintf('Step 1: the event queue is reachable and holds %d event(s).', $count));

        self::assertGreaterThanOrEqual(0, $count);
    }

    public function test_2_editing_a_job_queues_an_event(): void
    {
        $key = $this->optionalEnvironmentValue('SAGE_INTACCT_TRIGGER_TEST_JOB_KEY')
            ?? self::markTestSkipped('Set SAGE_INTACCT_TRIGGER_TEST_JOB_KEY to the key of a sandbox job this test may edit.');

        $client = $this->client();
        $job = $client->projects->get(new ObjectKey($key));
        $before = $client->eventQueue->count();

        $this->report();
        $this->report(sprintf('Step 2: editing job %s "%s"; %d event(s) queued before.', $job->id->value, $job->name, $before));

        $client->projects->update($job->key, UpdateProject::name($job->name)
            ->withDescription('Trigger test '.gmdate('Y-m-d H:i:s').' UTC'));

        try {
            $after = $this->waitForQueueToGrow($before);
        } finally {
            $client->projects->update($job->key, UpdateProject::name($job->name)->withDescription($job->description));
            $this->report('        restored the original description (this queues one more event).');
        }

        self::assertGreaterThan($before, $after, sprintf(
            'No event was queued within %d seconds. Check that the trigger is deployed, fires after update, '
            .'its condition is true, its client ID matches SAGE_INTACCT_CLIENT_ID, and that Job is the '
            .'projects/project object in this company.',
            self::WAIT_SECONDS,
        ));

        $this->report(sprintf('        the queue grew from %d to %d event(s).', $before, $after));
    }

    public function test_3_queued_events_can_be_read_without_removing_them(): void
    {
        $queue = $this->client()->eventQueue;
        $batch = $queue->list();

        $this->report();

        if ($batch->isEmpty()) {
            $this->report('Step 3: the queue is empty.');
            self::markTestSkipped('No queued events. Change a job in Sage, or set SAGE_INTACCT_TRIGGER_TEST_JOB_KEY so step 2 does it, then rerun.');
        }

        $this->report(sprintf('Step 3: read %d event(s); ackId %s. Nothing was removed.', count($batch->events), $batch->ackId ?? '(none)'));

        foreach ($batch->events as $number => $event) {
            $this->describe($number + 1, $event);
        }

        self::assertNotNull($batch->ackId, 'A non-empty batch must carry an ackId.');

        foreach ($batch->events as $event) {
            self::assertNotSame('', $event->eventId);
        }
    }

    public function test_4_acknowledging_a_batch_removes_it_from_the_queue(): void
    {
        if ($this->optionalEnvironmentValue('SAGE_INTACCT_TRIGGER_TEST_ACKNOWLEDGE') !== 'true') {
            self::markTestSkipped('Set SAGE_INTACCT_TRIGGER_TEST_ACKNOWLEDGE=true to let this test permanently remove the first queued batch.');
        }

        $queue = $this->client()->eventQueue;
        $batch = $queue->list();

        if ($batch->isEmpty()) {
            self::markTestSkipped('No queued events to acknowledge.');
        }

        $acknowledged = array_map(static fn (QueuedEvent $event): string => $event->eventId, $batch->events);
        $result = $queue->acknowledge($batch);

        $this->report();
        $this->report(sprintf('Step 4: acknowledged %d event(s); Sage answered status "%s".', count($acknowledged), $result->status ?? '(none)'));

        self::assertTrue($result->isSuccessful(), 'Sage did not accept the acknowledgement. The {"ackId": ...} request body the SDK sends may need a different shape.');

        $remaining = array_map(static fn (QueuedEvent $event): string => $event->eventId, $queue->list()->events);

        self::assertSame([], array_values(array_intersect($acknowledged, $remaining)), 'Acknowledged events are still queued.');

        $this->report(sprintf('        the acknowledged events are gone; %d event(s) remain in the next batch.', count($remaining)));
    }

    private function waitForQueueToGrow(int $before): int
    {
        $deadline = time() + self::WAIT_SECONDS;

        do {
            sleep(self::POLL_SECONDS);
            $count = $this->client()->eventQueue->count();
            $this->report(sprintf('        waiting for the trigger... %d event(s) queued', $count));
        } while ($count <= $before && time() < $deadline);

        return $count;
    }

    private function describe(int $number, QueuedEvent $event): void
    {
        $context = $event->context;
        $payload = (string) preg_replace('/\s+/', ' ', $event->payload->raw);

        $this->report(sprintf('  #%d %s (delivery type: %s)', $number, $event->eventId, $event->eventType ?? '-'));

        if ($context === null) {
            $this->report('     context: not decodable');
        } else {
            $this->report(sprintf(
                '     object: %s (%s)  event: %s  key: %s  id: %s',
                $context->restObjectName() ?? '-',
                $context->objectName() ?? '-',
                $context->event() ?? '-',
                $context->recordKey() ?? '-',
                $context->recordId() ?? '-',
            ));
        }

        $this->report(sprintf(
            '     payload: %s, %d bytes, %s: %s',
            $event->payload->contentType ?? 'no content type',
            strlen($event->payload->raw),
            $event->payload->isJson() ? 'JSON object' : 'not a JSON object',
            strlen($payload) > self::PAYLOAD_PREVIEW ? substr($payload, 0, self::PAYLOAD_PREVIEW).'...' : $payload,
        ));
    }
}
