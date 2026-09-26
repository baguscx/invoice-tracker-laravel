<?php

use Illuminate\Support\Facades\Schedule;

// Due status dihitung real-time saat data dibaca, jadi tidak memerlukan trigger harian.
Schedule::command('model:prune')->daily();
