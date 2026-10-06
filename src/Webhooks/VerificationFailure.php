<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Webhooks;

/** Why a webhook request failed verification. Safe to log; never contains request data. */
enum VerificationFailure: string
{
    case MissingSignature = 'missing_signature';
    case MalformedSignature = 'malformed_signature';
    case UnsupportedAlgorithm = 'unsupported_algorithm';
    case InvalidSignature = 'invalid_signature';
    case InvalidIssuer = 'invalid_issuer';
    case Expired = 'expired';
    case IssuedInFuture = 'issued_in_future';
    case MissingPayloadSignature = 'missing_payload_signature';
    case PayloadMismatch = 'payload_mismatch';

    public function message(): string
    {
        return match ($this) {
            self::MissingSignature => 'The webhook request has no Sage Intacct signature.',
            self::MalformedSignature => 'The webhook signature is not a well-formed JWT.',
            self::UnsupportedAlgorithm => 'The webhook signature does not use HS256.',
            self::InvalidSignature => 'The webhook signature does not match the client secret.',
            self::InvalidIssuer => 'The webhook signature was not issued by Sage Intacct.',
            self::Expired => 'The webhook signature has expired.',
            self::IssuedInFuture => 'The webhook signature was issued in the future.',
            self::MissingPayloadSignature => 'The webhook signature does not carry a payload hash.',
            self::PayloadMismatch => 'The webhook body does not match the signed payload hash.',
        };
    }
}
