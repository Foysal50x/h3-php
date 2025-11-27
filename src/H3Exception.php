<?php

declare(strict_types=1);

namespace Foysal50x\H3;

use Exception;

/**
 * Exception class for H3 library errors.
 */
class H3Exception extends Exception
{
    /**
     * H3 error codes mapped to descriptions.
     */
    private const ERROR_MESSAGES = [
        0 => 'Success',
        1 => 'The operation failed but a more specific error is not available',
        2 => 'Argument was outside of acceptable range',
        3 => 'Latitude or longitude arguments were outside of acceptable range',
        4 => 'Resolution argument was outside of acceptable range',
        5 => 'H3Index cell argument was not valid',
        6 => 'H3Index directed edge argument was not valid',
        7 => 'H3Index undirected edge argument was not valid',
        8 => 'H3Index vertex argument was not valid',
        9 => 'Pentagon distortion was encountered',
        10 => 'Duplicate input was encountered in the arguments',
        11 => 'H3Index cell arguments were not neighbors',
        12 => 'H3Index cell arguments had incompatible resolutions',
        13 => 'Memory allocation failed',
        14 => 'Bounds of provided memory were not large enough',
        15 => 'Mode or flags argument was not valid',
    ];

    private int $h3ErrorCode;

    /**
     * Create a new H3Exception.
     *
     * @param string $message Error message.
     * @param int $h3ErrorCode H3 error code.
     */
    public function __construct(string $message, int $h3ErrorCode = 0)
    {
        $this->h3ErrorCode = $h3ErrorCode;

        $errorDescription = self::ERROR_MESSAGES[$h3ErrorCode] ?? 'Unknown error';
        $fullMessage = "$message: $errorDescription (error code: $h3ErrorCode)";

        parent::__construct($fullMessage, $h3ErrorCode);
    }

    /**
     * Get the H3 error code.
     *
     * @return int H3 error code.
     */
    public function getH3ErrorCode(): int
    {
        return $this->h3ErrorCode;
    }

    /**
     * Get the H3 error description.
     *
     * @return string Error description.
     */
    public function getH3ErrorDescription(): string
    {
        return self::ERROR_MESSAGES[$this->h3ErrorCode] ?? 'Unknown error';
    }

    /**
     * Check if the error is due to an invalid cell.
     *
     * @return bool True if invalid cell error.
     */
    public function isInvalidCell(): bool
    {
        return $this->h3ErrorCode === 5;
    }

    /**
     * Check if the error is due to an invalid resolution.
     *
     * @return bool True if invalid resolution error.
     */
    public function isInvalidResolution(): bool
    {
        return $this->h3ErrorCode === 4;
    }

    /**
     * Check if the error is due to pentagon distortion.
     *
     * @return bool True if pentagon error.
     */
    public function isPentagonError(): bool
    {
        return $this->h3ErrorCode === 9;
    }

    /**
     * Check if the error is due to cells not being neighbors.
     *
     * @return bool True if not neighbors error.
     */
    public function isNotNeighborsError(): bool
    {
        return $this->h3ErrorCode === 11;
    }
}
