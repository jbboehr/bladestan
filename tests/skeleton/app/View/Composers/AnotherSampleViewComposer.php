<?php

namespace App\View\Composers;

use Illuminate\Contracts\View\View;

final class AnotherSampleViewComposer
{
    public function compose(View $view): void
    {
        if (random_int(0, 100) > 50) {
            $view->with('count', 3);
            /** @phpstan-ignore-next-line if.alwaysTrue */
            if (true) {
                $view->with('super_foo', 'super_bar');
            }
        } else {
            $view->with('count', 4);
        }
    }
}
