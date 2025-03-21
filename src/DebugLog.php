<?php declare(strict_types=1);

namespace AP\Logger\Dumper;

use AP\Logger\Action;
use AP\Logger\Level;
use Closure;
use DateTime;
use DateTimeZone;
use Throwable;

/**
 * Logs messages using PHP's built-in error_log function and to a specified file
 *
 * Supports configurable log level, context printing, stack trace printing,
 * and custom time formatting with an optional timezone. The log messages
 * are written in append mode, ensuring that new logs do not overwrite
 * previous ones.
 *
 * The log format includes timestamps, log levels, and module names,
 * with optional context and backtrace details for debugging purposes.
 *
 * Note: `error_log()` has a system-dependent message length limit.
 * Typically, on Linux, the limit is around 1024 bytes for syslog.
 * If the message exceeds this limit, it may be truncated.
 */
readonly class DebugLog implements AddInterface
{
    /**
     * Initializes the FileLog instance with optional configurations
     *
     * @param string $filename
     * @param bool $add_to_error_log
     * @param Level $log_level Minimum log level required for a message to be logged
     * @param bool $print_context Whether to include context data in the log output
     * @param bool $print_trace Whether to include stack trace information in the log output
     * @param string|null $timezone Timezone for formatting log timestamps
     * @param string $date_format Format for displaying timestamps
     * @param ?Closure|string|array $message_decorator Callable to modify the log message output
     *                                     Function signature: function(AP\Logger\Action $action): string
     */
    public function __construct(
        public string                    $filename = "",
        public bool                      $add_to_error_log = true,
        public Level                     $log_level = Level::INFO,
        public bool                      $print_context = true,
        public bool                      $print_trace = false,
        public ?string                   $timezone = null,
        public string                    $date_format = "Y-m-d H:i:s.u",
        public null|Closure|string|array $message_decorator = null,
        public bool                      $show_prefix = true,
    )
    {
    }

    private function formatTime(float $microtime): string
    {
        $dt = DateTime::createFromFormat(
            'U.u',
            number_format(
                $microtime,
                6,
                '.',
                ''
            )
        );

        if (is_string($this->timezone)) {
            try {
                $dt->setTimeZone(new DateTimeZone($this->timezone));
            } catch (Throwable) {
            }
        }

        return $dt->format($this->date_format);
    }

    /**
     * Logs an action if its level meets or exceeds the configured log level
     *
     * Note: `error_log()` has a system-dependent message length limit.
     * Typically, on Linux, the limit is around 1024 bytes for syslog.
     * If the message exceeds this limit, it may be truncated.
     *
     * @param Action $action The log action to be recorded
     */
    public function add(Action $action): void
    {
        $time    = $this->formatTime($action->microtime);
        $level   = $action->level->name;
        $message = $action->message;
        if (is_callable($this->message_decorator)) {
            $message = (string)($this->message_decorator)($action);
        }
        $message = $this->show_prefix
            ? ["$time $action->module::[$level] $message"]
            : [$message];

        if ($this->print_context && count($action->context)) {
            $message[] = "  data:";
            $message[] = substr(print_r($action->context, true), 8, -3);
        }

        if ($this->print_trace) {
            $indent    = str_repeat(" ", 4) . "- ";
            $message[] = "  trace:";
            $message[] =
                $indent . implode("\n$indent",
                    array_map(
                        function ($el) {
                            return ($el['file'] ?? "") . ":" . ($el['line'] ?? "0");
                        },
                        $action->backtrace
                    ),
                ) . "\n";
        }

        if ($action->level->value >= $this->log_level->value) {
            if (file_exists($this->filename)) {
                file_put_contents(
                    $this->filename,
                    implode("\n", $message) . "\n",
                    FILE_APPEND | LOCK_EX
                );
            }

            if ($this->add_to_error_log) {
                error_log(implode("\n", $message));
            }
        }
    }
}