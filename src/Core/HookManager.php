<?php

declare(strict_types=1);

namespace ERTAppointment\Core;

/**
 * Collects WordPress action/filter registrations and applies them in one shot.
 * Keeps Plugin.php clean and makes the hook list easy to audit.
 */
final class HookManager
{
    /** @var list<array{type:string, hook:string, callback:callable, priority:int, args:int}> */
    private array $hooks = [];

    /**
     * Queues an action hook.
     */
    public function add(
        string   $hook,
        callable $callback,
        int      $priority = 10,
        int      $acceptedArgs = 1
    ): self {
        $this->hooks[] = [
            'type'     => 'action',
            'hook'     => $hook,
            'callback' => $callback,
            'priority' => $priority,
            'args'     => $acceptedArgs,
        ];

        return $this;
    }

    /**
     * Queues a filter hook.
     */
    public function filter(
        string   $hook,
        callable $callback,
        int      $priority = 10,
        int      $acceptedArgs = 1
    ): self {
        $this->hooks[] = [
            'type'     => 'filter',
            'hook'     => $hook,
            'callback' => $callback,
            'priority' => $priority,
            'args'     => $acceptedArgs,
        ];

        return $this;
    }

    /**
     * Registers all queued hooks with WordPress.
     */
    public function register(): void
    {
        foreach ($this->hooks as $hook) {
            if ($hook['type'] === 'action') {
                add_action($hook['hook'], $hook['callback'], $hook['priority'], $hook['args']);
            } else {
                add_filter($hook['hook'], $hook['callback'], $hook['priority'], $hook['args']);
            }
        }
    }
}
