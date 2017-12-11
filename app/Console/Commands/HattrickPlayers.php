<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Classes\Hattrick as HattrickClass;
use League\Csv\Reader;

class HattrickPlayers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hattrick:players';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(HattrickClass $hattrick)
    {
        parent::__construct();
        $this->hattrick = $hattrick;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $data = $this->importData();
        $data = $this->processData($data);
        dd($data);
    }

    private function importData() {
        $reader = Reader::createFromPath('database/csv/playerexport.csv', 'r');
        $reader->setHeaderOffset(0);
        $records = $reader->getRecords();
        $data = [];
        foreach ($records as $player) {
            $data[] = $player;
        }
        return $data;
    }

    private function processData($data) {
        $result = [];
        $data = collect($data)->map(function($item) {
            return [
                'name' => $item['name'],
                'age' => $item['age'],
                'days' => $item['agedays'],
                'forma' => $item['form'],
                'condicion' => $item['stamina'],
                'tsi' => $item['tsi'],
                'experiencia' => $item['xp'],
                'skills' => [
                    'porteria' => $item['skill_gk'],
                    'jugadas' => $item['skill_pm'],
                    'lateral' => $item['skill_wi'],
                    'anotacion' => $item['skill_sc'],
                    'pases' => $item['skill_ps'],
                    'defensa' => $item['skill_de'],
                    'balon_parado' => $item['skill_setpieces'],
                ]
            ];
        })->map(function($player) {
            $item = [];
            $item['name'] = $player['name'];
            $item['age'] = $player['age'];
            //$item['stars'] = $this->hattrick->calculateBestStars($player);
            $item['best_stars'] = $this->hattrick->getBestStar($player);
            $item['position'] = $this->hattrick->getPosition($player);
            $item['stars_now'] = $this->hattrick->getBestStarNow($player);
            $item['htms'] = $this->hattrick->getHTMS($player);
            return $item;
        })->each(function($item) use (&$result) {
            $key = $item['position'];
            unset($item['position']);
            $result[$key][] = $item;
        });

        return collect($result)->map(function($item) {
            return collect($item)->sortBy('best_stars')->values();  
        });
    }
}
