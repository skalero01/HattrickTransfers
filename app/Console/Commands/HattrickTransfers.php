<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Classes\Hattrick as HattrickClass;
use App\Classes\Browser;

class HattrickTransfers extends Command
{

    protected $signature = 'hattrick:transfers';
    protected $description = 'Check prices';

    protected $search = [
        // 5 stars
        [
            'years'    => [27, 38], 
            'skill'    => [5 => [7, 20]],
            'prices'   => [0, 200000],
            'stars'    => 5,
            'position' => ['Lateral']
        ]
    ];

    public function __construct(HattrickClass $hattrick, Browser $browser)
    {
        parent::__construct();
        $this->hattrick = $hattrick;
        $this->browser = $browser;
    }

    public function handle()
    {
        $searchs = $this->hattrick->convertSearch($this->search);
        $this->browser->browse(function ($browpage) use ($searchs) {
            $this->hattrick->setBrowser($browpage);
            $this->hattrick->logIn();

            while(true) {
                $this->hattrick->makeMasiveTransferSearch($searchs);
                $this->hattrick->huntPlayers();
            }
        });
    }
    
}
