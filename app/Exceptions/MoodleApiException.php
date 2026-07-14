<?php

namespace App\Exceptions;

use Exception;

class MoodleApiException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?string $moodleErrorCode = null,
        public readonly int $status = 502,
    ) {
        parent::__construct($message);
    }

    public static function fromMoodleResponse(array $response): self
    {
        $errorCode = $response['errorcode'] ?? null;
        $message = $response['message'] ?? 'Unknown error returned by Moodle.';

        $status = match ($errorCode) {
            'invalidtoken', 'accessexception' => 401,
            'nopermissions' => 403,
            'invalidparameter', 'invalidrecord', 'wsinvalidparam' => 422,
            default => 502,
        };

        return new self($message, $errorCode, $status);
    }

    public static function connectionFailed(string $reason): self
    {
        return new self("Unable to reach Moodle: {$reason}", null, 502);
    }
}
