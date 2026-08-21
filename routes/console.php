<?php
use Illuminate\Support\Facades\Schedule;Schedule::command('merchant:sync --stale')->dailyAt('02:00')->withoutOverlapping();Schedule::command('queue:prune-failed --hours=168')->daily();
