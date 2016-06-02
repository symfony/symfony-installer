<?php

namespace Symfony\Installer\Exception;

/**
 * This exception is thrown when the execution is aborted with the SIGINT signal.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class AbortException extends \RuntimeException
{
}
