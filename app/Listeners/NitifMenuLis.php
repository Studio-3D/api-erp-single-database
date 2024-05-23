<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;



class NotifiEventEventListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**

     *
     * @param  \App\Events\YourEvent  $event
     * @return void
     */
    public function handle($event)
    {
        $data = $event->data;
    }
}
