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
        // Lateral
        [
            'years'    => [17, 19],
            'skill'    => [5 => [7, 10]],
            'prices'   => [0, 500000],
            'potential'    => 2000,
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
