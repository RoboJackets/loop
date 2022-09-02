<?php

declare(strict_types=1);

namespace App\Nova\Dashboards;

use Laravel\Nova\Cards\Help;
use Laravel\Nova\Dashboards\Main as Dashboard;

class Main extends Dashboard
{
    /**
     * Get the cards for the dashboard.
     *
     * @return array<\Laravel\Nova\Cards\Help>
     */
    public function cards(): array
    {
        return [
            new Help(),
        ];
    }
}
