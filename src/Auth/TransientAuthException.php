<?php

namespace Dev1\NotifyCore\Auth;

/**
 * Marker exception for transient OAuth failures that may succeed on retry
 * (5xx responses, 429, and PSR-18 transport errors).
 */
final class TransientAuthException extends \RuntimeException
{
}
