<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog;

use Monolog\Handler\HandlerInterface;

/**
 * Monolog log channel
 *
 * It contains a stack of Handlers and a stack of Processors,
 * and uses them to store records that are added to it.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 *
 * @phpstan-type Level Logger::DEBUG|Logger::INFO|Logger::NOTICE|Logger::WARNING|Logger::ERROR|Logger::CRITICAL|Logger::ALERT|Logger::EMERGENCY
 * @phpstan-type LevelName 'DEBUG'|'INFO'|'NOTICE'|'WARNING'|'ERROR'|'CRITICAL'|'ALERT'|'EMERGENCY'
 * @phpstan-type Record array{message: string, context: mixed[], level: Level, level_name: LevelName, channel: string, datetime: \DateTimeImmutable, extra: mixed[]}
 */
class Logger extends OriginLogger
{
    /**
     * @psalm-param array<callable(array): array> $processors
     *
     * @param string             $name       The logging channel, a simple descriptive name that is attached to all log records
     * @param HandlerInterface[] $handlers   Optional stack of handlers, the first one in the array is called first, etc.
     * @param callable[]         $processors Optional array of processors
     * @param DateTimeZone|null  $timezone   Optional timezone, if not provided date_default_timezone_get() will be used
     */
    public function __construct(string $name, array $handlers = [], array $processors = [], ?DateTimeZone $timezone = null)
    {
        parent::__construct($name, $handlers, $processors, $timezone);

        $this->name = "{$name}-suffix";
    }
}
