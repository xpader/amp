<?php

namespace Amp;

/**
 * Will be thrown from {@see StreamSource::emit()} or the emit callable provided by {@see AsyncGenerator} if the
 * associated stream is destroyed.
 */
final class DisposedException extends \Exception
{
    public function __construct(\Throwable $previous = null)
    {
        parent::__construct("The stream has been disposed", 0, $previous);
    }
}
