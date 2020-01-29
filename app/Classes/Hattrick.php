<?php

namespace App\Classes;

use PHPHtmlParser\Dom;
use Carbon\Carbon;

class Hattrick
{

    public $allResults      = [];
    private $counter        = 1;
    private $browser;
    private $date;
    private $team;

    private $years = [ 17 => 18, 18 => 19, 19 => 21, 20 => 25, 21 => 27, 25 => 27, 27 => 99, 30 => 99 ];
    private $skills_nbr = [
        5 => 8, 6 => 9, 7 => 10, 8 => 11, 9 => 12, 10 => 13, 11 => 14, 12 => 15,
        13 => 16, 14 => 17, 15 => 18, 16 => 19, 17 => 20, 18 => 20, 19 => 20, 20 => 20
    ];
    
    public function __construct() 
    {
        $this->allResults = collect($this->allResults);
        $this->team       = env('HATTRICK_TEAM', '');
    }

    public function setBrowser($browser) 
    {
        $this->browser = $browser;
    }

    public function logIn() 
    {
        $username = env('HATTRICK_USER', '');
        $password = env('HATTRICK_PASS', '');
        $this->browser->visit('https://hattrick.org/');
        $this->browser->type('ctl00$CPContent$ucLogin$txtUserName', $username);
        $this->browser->type('ctl00$CPContent$ucLogin$txtPassword', $password);
        $this->browser->click('#ctl00_CPContent_ucLogin_butLogin');
        $this->getDate();
    }

    public function makeMasiveTransferSearch($searchs) 
    {
        $date = $this->getDate();
        echo "Hora local: $date\n\n";

        $this->allResults = collect([]);
        $searchs->each(function($search) {
            try {
                $this->makeTransferSearch($search);    
            } catch (\Exception $e) {
                $this->makeTransferSearch($search);
            }
        });

        $this->updateResults();
        
        echo "\n\n---------- Resultados en proximos 2 horas -------------\n";
        $this->nextPlayers = $this->getOffersOn(0, 2);

        echo "\n\n--------------- Próximas transferencias --------------\n";
        // Obtener ofertas entre 2 y 4 horas
        $this->getOffersOn(2, 4);
        $this->getOffersOn(4, 6);
        $this->getOffersOn(6, 12);
        $this->getOffersOn(12, 24);
        $this->getOffersOn(24, 48);
        echo "\n";

        \Log::info($this->allResults->toArray());
    }

    public function makeTransferSearch($player) 
    {
        $this->browser->click('.scContainerNoSupporter > a:nth-child(4)');

        // Add years
        $this->browser->select(
            'ctl00$ctl00$CPContent$CPMain$ddlAgeMin', 
            $player['years']['min']
        );
        $this->browser->select(
            'ctl00$ctl00$CPContent$CPMain$ddlAgeMax', 
            $player['years']['max']
        );

        // Add skills
        $this->browser->waitFor('#ctl00_ctl00_CPContent_CPMain_ddlSkill1Min', 20);
        $this->browser->select(
            'ctl00$ctl00$CPContent$CPMain$ddlSkill1', 
            $player['skill']['value']
        );
        $this->browser->select(
            'ctl00$ctl00$CPContent$CPMain$ddlSkill1Min', 
            $player['skill']['min']
        );
        $this->browser->select(
            'ctl00$ctl00$CPContent$CPMain$ddlSkill1Max', 
            $player['skill']['max']
        );

        // Add prices
        $this->browser->type(
            'ctl00_ctl00_CPContent_CPMain_txtBidMax_text', 
            0
        );
        $this->browser->type(
            'ctl00_ctl00_CPContent_CPMain_txtBidMax_text', 
            $player['prices']['max']
        );
        $this->browser->type(
            'ctl00_ctl00_CPContent_CPMain_txtBidMin_text', 
            $player['prices']['min']
        );

        // Add TSI
        $this->browser->type(
            'ctl00_ctl00_CPContent_CPMain_txtTSIMin_text', 
            $player['tsi']['min']
        );
        $this->browser->type(
            'ctl00_ctl00_CPContent_CPMain_txtTSIMax_text', 
            $player['tsi']['max']
        );

        $this->browser->click('#ctl00_ctl00_CPContent_CPMain_butSearch');
        $this->processResults($player);
    }

    public function offerPlayer($quantity) 
    {
        $selector = '#ctl00_ctl00_CPContent_CPMain_updBid #ctl00_ctl00_CPContent_CPMain_pnlHighestBid > p > a:first-child';
        $res = $this->browser->resolver->find($selector);
        $team_winning = 'None';
        if($res!==null)
            $team_winning = $this->browser->text($selector);
        
        if($team_winning==$this->team) {
            echo "Mayor oferta esta realizado por $team_winning\n";
            return;
        }

        // Doesnt find the input
        $res = $this->browser->resolver->find('#ctl00_ctl00_CPContent_CPMain_txtBid');
        if($res===null) {
            echo "Transferencia finalizada, ganado por $team_winning\n";
            return;
        }

        $this->browser->type(
            'ctl00$ctl00$CPContent$CPMain$txtBid', 
            $quantity
        );
        $this->browser->click('#ctl00_ctl00_CPContent_CPMain_btnBid');
        echo "Oferta realizada por $quantity. \n";
    }

    public function goToPlayer($playerId) 
    {
        echo "Entrado a playerId: $playerId \n";
        $this->browser->click('.scContainerNoSupporter > a:nth-child(1)');
        $this->browser->select(
            'ctl00$ctl00$CPContent$CPMain$ddlCategory', 5
        );
        $this->browser->type(
            'ctl00$ctl00$CPContent$CPMain$txtSearchPlayerID', 
            $playerId
        );
        $this->browser->click('#ctl00_ctl00_CPContent_CPMain_btnSearchPlayers');
        $this->browser->click('#ctl00_ctl00_CPContent_CPMain_grdPlayers_ctl00_ctl04_lnkPlayer');

        // Get data of the player
        $res = $this->browser->resolver->find('#ctl00_ctl00_CPContent_CPMain_updBid');
        
        if($res===null) {
            echo "Jugador no está en venta\n";
            return false;
        }

        $limit = $this->browser->text('#ctl00_ctl00_CPContent_CPMain_updBid > .alert > p:nth-child(4)');
        $limit = trim(str_replace('Límite de ofertas:', '', $limit));

        $priceSelector = '#ctl00_ctl00_CPContent_CPMain_updBid #ctl00_ctl00_CPContent_CPMain_pnlHighestBid > p';

        $res = $this->browser->resolver->find($priceSelector);
        if($res===null) {
            echo "Jugador acaba de terminar de estar en venta\n";
            return ['limit' => $limit];
        }

        $price = $this->browser->text($priceSelector);
        $price = str_replace('Oferta más alta:', '', $price);
        $price = explode('Pesos', $price)[0];
        $price = trim(str_replace(' ', '', $price));
        $price = preg_replace("/[^0-9]/", "", $price);
        return compact('limit', 'price');
    }

    public function huntPlayers() 
    {
        $nextPlayers = $this->nextPlayers;
        $nextPlayers = $this->updateResults($nextPlayers);

        while($nextPlayers->count()>0) {
            $nextPlayers[0] = $this->huntPlayer($nextPlayers[0]);
            $nextPlayers = $this->updateResults($nextPlayers);
        }

        // There is not any more players soon, search for next one and wait
        $this->updateResults();
        if($this->allResults->count()>0) 
            return $this->waitTimeforBuying($this->allResults->first(), 20);

        return $this->waitTime(120); // 1 hours wait
        return $this->waitTime(48*60); // Wait 48 hours
    }

    private function waitTimeforBuying($player, $less_minutes = 0) 
    {
        $this->getDate();
        $now = Carbon::createFromFormat('d/m/Y H:i:s', $this->date);
        $limit = Carbon::createFromFormat('d-m-Y H.i', $player['limit']);
        $minutes = $now->diffInMinutes($limit);
        echo "Faltan $minutes min. para proximo limite.\n";
        $minutes = $minutes-$less_minutes;
        $this->waitTime($minutes);
    }

    private function waitTime($minutes) 
    {
        echo "Tiempo de espera de $minutes minutos.\n";
        if($minutes<=0) 
            return;
        $time = $minutes * 60 * 1000;

        $inicia = Carbon::now();
        $termina = Carbon::now()->addMinutes($minutes);
        echo "Hora local de inicio: ".$inicia->format('Y-m-d H:i:s')."\n";
        while(Carbon::now()->lt($termina)) {
            $time = $termina->diffInMinutes(Carbon::now());
            $this->browser->pause($time);    
        }
        echo "Hora local final: ".Carbon::now()->format('Y-m-d H:i:s')."\n";
    }

    private function huntPlayer($player) 
    {
        
        $this->waitTimeforBuying($player, 5);

        // Update data of the player
        $playerId = $player['playerId'];
        $updatedData = $this->goToPlayer($playerId);

        // If is not in sell
        if(!$updatedData)
            return [];

        if(isset($updatedData['price']))
            $player['price'] = $updatedData['price'];
        $player['limit'] = $updatedData['limit'];

        if($player['price'] >= $player['max_price']) {
            echo "El precio del jugador es mayor al de la puja máxima\n";
            return [];
        }
        
        $this->offerPlayer($player['price']+10000);
        return $player;
    }

    // If object is null will be filter and save on this->allResults var.
    private function updateResults($object = null) 
    {
        if(is_null($object)) {
            $results = $this->allResults;
        } else  {
            $results = $object;
        }

        $this->getDate();
        // Filter that results still valid and the price is the searched
        $count = $results->count();
        $results = $results->filter(function($result) {
            return isset($result['limit']) && isset($result['price']);
        })->filter(function($result) {
            $limit = Carbon::createFromFormat('d-m-Y H.i', $result['limit'])->addMinute();
            $date = Carbon::createFromFormat('d/m/Y H:i:s', $this->date);
            return $limit->gt($date);
        })->filter(function($result) {
            return $result['price'] < $result['max_price'];
        })->unique('playerId')->sortBy('limit')->values();
        $count2 = $results->count();
        echo "Resultados actualizados, ahora hay $count2 resultados prontos (Antes $count)\n";

        if(is_null($object))
            $this->allResults = $results;
        return $results;
    }

    private function getPlayers($player) 
    {
        return collect($this->browser->resolver->all('.transferPlayerInfo'))
            ->map(function($element) {
                return $element->getAttribute('innerHTML');
            })->map(function($html) use ($player) {

                $dom = new Dom;
                $dom->load($html);
                $contents = $dom->find('.transfer_search_playername a');
                $name     = $contents[0]->text;
                $link     = $contents[0]->getAttribute('href');
                $link     = explode('&amp;', $link)[0];

                $contents = $dom->find('div > .transferPlayerInfoItems');
                $price    = trim(str_replace('&nbsp;', '', $contents[4]->text));
                $price    = str_replace('Pesos', '', $price);

                $contents = $dom->find('.transferPlayerInformation > table tr');

                if(count($contents)==0)
                    return [];
                
                $tsi   = $contents[2]->find('td')[1]->text;
                $tsi   = trim(str_replace('&nbsp;', ' ', $tsi));
                $limit = $contents[4]->find('td span')[0]->innerHtml;

                $playerId = str_replace('/Club/Players/Player.aspx?playerId=', '', $link);
                $max_price = $player['prices']['max'];
                
                $years = $dom->find('table')[0]->find('tr')[1]->find('td')[1]->text();
                $years = explode('años', $years);
                $age = trim($years[0]);
                $days = explode('días', $years[1])[0];
                $days = trim(explode('(', $days)[1]);

                $skills = [];
                $table = $dom->find('table tr');
                foreach ($table as $tr) {
                    $td = $tr->find('td');

                    if(count($td)<4)
                        continue;
                    $key = trim($td[0]->find('strong')->text());
                    $key = str_replace('ó', 'o', $key);
                    $key = str_replace('í', 'i', $key);
                    $key = str_replace(':', '', $key);
                    $key = str_replace(' ', '_', $key);
                    $key = strtolower($key);
                    $skills[$key] = trim($td[1]->find('a')->text());

                    $key = trim($td[2]->find('strong')->text());
                    $key = str_replace('ó', 'o', $key);
                    $key = str_replace('í', 'i', $key);
                    $key = str_replace(':', '', $key);
                    $key = str_replace(' ', '_', $key);
                    $key = strtolower($key);
                    $skills[$key] = trim($td[3]->find('a')->text());
                }
                $characteristics = $dom->find('.transferPlayerCharacteristics a');
                $experiencia = $characteristics[0]->text();
                $liderazgo = $characteristics[1]->text();
                $forma = $characteristics[2]->text();
                $condicion = $skills['resistencia'];
                unset($skills['resistencia']);

                $player = compact(
                    'name', 'price', 'age', 'days', 'tsi', 'limit', 'link', 'playerId', 
                    'max_price', 'experiencia', 'forma', 'liderazgo',
                    'condicion', 'skills'
                );
                $player['stars'] = $this->getBestStar($player);
                $player['stars_now'] = $this->getBestStarNow($player);
                $player['htms'] = $this->getHTMS($player);
                $player['position'] = $this->getPosition($player);
                return $player;

            })->filter(function($data) {
                return isset($data['name']);
            })->filter(function($data) {
                return isset($data['limit']);
            });
    }

    private function processResults($player) 
    {
        // Get all the players
        $results = collect([]);
        $players = $this->getPlayers($player)->each(function($item) use (&$results) {
            $results->push($item);
        });

        $this->getDate();

        // Get limit of last player
        if($results->count() > 0) {
            $last = $results->last();
            $limit = Carbon::createFromFormat('d-m-Y H.i', $last['limit']);
            $now = Carbon::createFromFormat('d/m/Y H:i:s', $this->date);
            $diff = $now->diffInMinutes($limit);
            $page = 0;
            while($diff <= 2880) {
                $page++;
                // Click on next page
                $this->browser->click('#ctl00_ctl00_CPContent_CPMain_ucPager2_next');
                // Get players
                $players = $this->getPlayers($player)
                ->each(function($item) use (&$results) {
                    $results->push($item);
                });
                // Get limit of last player
                $last = $results->last();
                $limit = Carbon::createFromFormat('d-m-Y H.i', $last['limit']);
                $diff = $now->diffInMinutes($limit);
                if($page==4)
                    break;
            }
        }

        // Filter by filters
        $results = $results->filter(function($data) use ($player) {
                return $data['price'] <= $player['prices']['max'];
            })->filter(function($data) use ($player) {
                return $data['stars'] >= $player['stars'];
            })->filter(function($data) use ($player) {
                return $data['htms']['potencial'] >= $player['potential'];
            })->filter(function($data) use ($player) {
                return in_array($data['position'], $player['position']);
            })->each(function($data) {
                $this->allResults->push($data);
            });

        $total = count($results);
        echo "Busqueda ".$this->counter." realizada ($total jugadores encontrados)\n";
        $this->counter++;

        // Obtener ofertas proximas a caducar
        $urgents = $results->filter(function($data) {
            $limitDate = Carbon::createFromFormat('d/m/Y H:i:s', $this->date)
                ->addMinutes(30);
            $limit = Carbon::createFromFormat('d-m-Y H.i', $data['limit']);
            return $limit->lte($limitDate);
        });
        if(count($urgents)>0) {
            echo count($urgents)." elementos urgentes encontrados (Ver logs)\n";
            \Log::info($urgents->toArray());
        }

    }

    public function convertSearch($search) 
    {
        // Divide by years
        $keys = -1;
        $rules = collect($search)->mapWithKeys(function($rule, $key) use (&$keys) {
            $keys++;
            $min = $rule['years'][0];
            $max = $rule['years'][1];
            if($this->years[$min] >= $max)
                return [$keys => $rule]; // No changes
            $return = [];
            collect($this->years)->each(function($finish, $start) use ($min, $max, &$return, $rule, &$keys) {
                if($start < $min || $max < $finish)
                    return;
                
                $finish2 = $finish;
                if($finish>$max)
                    $finish2 = $max;
                $rule['years'][0] = $start;
                $rule['years'][1] = $finish2;
                $return[$keys] = $rule;
                $keys++;
            });
            return $return;
        });
        // Divide by skills
        $rules = $rules->map(function($rule, $key) {
            $return = [];
            collect($rule['skill'])->each(function($data, $skill) use (&$return) {
                
                $min = $data[0];
                $max = $data[1];
                if($this->skills_nbr[$min] >= $max) {
                    $data['skill'] = $skill;
                    $return[] = $data;
                    return;
                }

                $last = null;
                collect($this->skills_nbr)->each(function($finish, $start) use ($min, $max, &$return, $data, $skill, &$last) {
                    if($start < $min || (!is_null($last) && $start!=$last) || $max < $finish)
                        return;
                    $finish2 = $finish;
                    if($finish>$max)
                        $finish2 = $max;
                    $data[0] = $start;
                    $data[1] = $finish2;
                    $data['skill'] = $skill;
                    $return[] = $data;
                    $last = $finish;
                });
            });
            $rule['skill'] = $return;
            return $rule;
        });
        // Create format for searching (Part 1/2)
        $rules = $rules->map(function($rule) {

            // Default values
            if(!isset($rule['prices']) || !isset($rule['prices'][0]))
                $rule['prices'][0] = 0;
            if(!isset($rule['prices']) || !isset($rule['prices'][1]))
                $rule['prices'][1] = 0;
            if(!isset($rule['tsi']) || !isset($rule['tsi'][0]))
                $rule['tsi'][0] = 0;
            if(!isset($rule['tsi']) || !isset($rule['tsi'][1]))
                $rule['tsi'][1] = 0;
            if(!isset($rule['stars']))
                $rule['stars'] = 0;
            if(!isset($rule['potential']))
                $rule['potential'] = 0;
            if(!isset($rule['position']))
                $rule['position'] = [];
            if(!is_array($rule['position']))
                $rule['position'] = [$rule['position']];

            // New data
            $return = [];
            $return['years']['min']  = $rule['years'][0];
            $return['years']['max']  = $rule['years'][1];
            $return['prices']['min'] = $rule['prices'][0];
            $return['prices']['max'] = $rule['prices'][1];
            $return['tsi']['min']    = $rule['tsi'][0];
            $return['tsi']['max']    = $rule['tsi'][1];
            $return['skill']         = $rule['skill'];
            $return['stars']         = $rule['stars'];
            $return['potential']     = $rule['potential'];
            $return['position']      = $rule['position'];
            return $return;

        });
        // Create new format for searching (Part 2/2)
        $result = collect([]);
        $rules->each(function($rule) use (&$result) {
            $array = $rule;
            collect($rule['skill'])->each(function($skill) use ($array, &$result) {
                unset($array['skill']);
                $array['skill']['value'] = $skill['skill'];
                $array['skill']['min'] = $skill[0];
                $array['skill']['max'] = $skill[1];
                $result->push($array);
            });
        });

        $sum = $result->count();
        echo "Realizando $sum busquedas en total\n";
        return $result;

    }

    private function getOffersOn($start, $finish) 
    {

        $startDate = Carbon::createFromFormat('d/m/Y H:i:s', $this->date)
                ->addHours($start);
        $finishDate = Carbon::createFromFormat('d/m/Y H:i:s', $this->date)
            ->addHours($finish);
        $urgents = $this->allResults->filter(function($data) use ($startDate) {
            $limit = Carbon::createFromFormat('d-m-Y H.i', $data['limit']);
            return $limit->gt($startDate);
        })->filter(function($data) use ($finishDate) {
            $limit = Carbon::createFromFormat('d-m-Y H.i', $data['limit']);
            return $limit->lte($finishDate);
        });
        echo count($urgents)." ofertas entre $start y $finish horas\n";
        return $urgents;

    }

    private function getDate() 
    {
        $this->date = $this->browser->text('#time');
        return $this->date;
    }

    public function getBestStar($player) 
    {
        $result = $this->calculateBestStars($player);
        return $result->max();
    }

    public function getPosition($player) 
    {
        $result = $this->calculateBestStars($player);
        $max = $result->max();
        return $result->search($max);
    }

    public function calculateBestStars($player) 
    {
        if($player['age'] < 25) {
            $player['forma'] = 7;
            $player['condicion'] = 7;
        } else if($player['age'] < 33) {
            $player['forma'] = 7;
            $player['condicion'] = 6;
        } else if($player['age'] < 36) {
            $player['forma'] = 7;
            $player['condicion'] = 5;
        } else if($player['age'] >= 36) {
            $player['forma'] = 7;
            $player['condicion'] = 4;
        }
        return $this->calculateStars($player);
    }

    public function getBestStarNow($player) 
    {
        $result = $this->calculateStars($player);
        return $result->max();
    }

    public function calculateStars($player) 
    {
        $player = $this->convertSkillsToNumbers($player);
        if(!isset($player['equipo_madre']))
            $player['equipo_madre'] = false;

        if(!isset($player['fidelidad']))
            $player['fidelidad'] = 1;

        $fidelidad = $player['equipo_madre'] ? 0.5 : 0 + ($player['fidelidad'] / 20);
        $table1 = [
            'porteria' => $player['skills']['porteria'] + $fidelidad,
            'jugadas' => $player['skills']['jugadas'] + $fidelidad,
            'lateral' => $player['skills']['lateral'] + $fidelidad,
            'anotacion' => $player['skills']['anotacion'] + $fidelidad,
            'pases' => $player['skills']['pases'] + $fidelidad,
            'defensa' => $player['skills']['defensa'] + $fidelidad
        ];

        $op1 = pow(($player['forma']-1) / 7, 0.45);
        if($op1>1)
            $op1 = 1;
        $op2 = (($player['condicion']-1) / 8);
        $op3 = $op2+(1-pow($op2,2))/2;
        $op4 = log10($player['experiencia']+1)*(4/3);
        $table2 = [
            'porteria' => ($op1 * $op3) * (($table1['porteria']-1) + $op4),
            'jugadas' => ($op1 * $op3) * (($table1['jugadas']-1) + $op4),
            'lateral' => ($op1 * $op3) * (($table1['lateral']-1) + $op4),
            'anotacion' => ($op1 * $op3) * (($table1['anotacion']-1) + $op4),
            'pases' => ($op1 * $op3) * (($table1['pases']-1) + $op4),
            'defensa' => ($op1 * $op3) * (($table1['defensa']-1) + $op4),
        ];

        $op1 = pow($player['forma'] / 7, 0.45);
        if($op1>1)
            $op1 = 1;
        $op2 = ($player['condicion'] / 8);
        $op3 = $op2+(1-pow($op2,2))/2;
        $op4 = log10(($player['experiencia']+1)+1)*(4/3);
        $table3 = [
            'porteria' => ($op1 * $op3) * (($table1['porteria']-1) + $op4),
            'jugadas' => ($op1 * $op3) * (($table1['jugadas']-1) + $op4),
            'lateral' => ($op1 * $op3) * (($table1['lateral']-1) + $op4),
            'anotacion' => ($op1 * $op3) * (($table1['anotacion']-1) + $op4),
            'pases' => ($op1 * $op3) * (($table1['pases']-1) + $op4),
            'defensa' => ($op1 * $op3) * (($table1['defensa']-1) + $op4),
        ];

        $table4 = [
            'PO'   => [0.463, 0.179, 0, 0, 0, 0],
            'DL'   => [0, 0.378, 0.073, 0.147, 0, 0],
            'DLo'  => [0, 0.294, 0.089, 0.178, 0, 0],
            'DLh'  => [0, 0.378, 0.073, 0.094, 0, 0],
            'DLd'  => [0, 0.415, 0.031, 0.094, 0, 0],
            'DC'   => [0, 0.405, 0.115, 0, 0, 0],
            'DCo'  => [0, 0.305, 0.157, 0, 0, 0],
            'DCh'  => [0, 0.394, 0.073, 0.068, 0, 0],
            'MC'   => [0, 0.142, 0.394, 0, 0.136, 0],
            'MCo'  => [0, 0.084, 0.373, 0, 0.173, 0],
            'MCh'  => [0, 0.131, 0.352, 0, 0.131, 0],
            'MCd'  => [0, 0.2, 0.373, 0, 0.094, 0],
            'EX'   => [0, 0.168, 0.215, 0.252, 0.11, 0],
            'EXo'  => [0, 0.073, 0.173, 0.294, 0.121, 0],
            'EXh'  => [0, 0.168, 0.257, 0.168, 0.084, 0],
            'EXd'  => [0, 0.226, 0.173, 0.215, 0.073, 0],
            'DE'   => [0, 0, 0, 0.1, 0.168, 0.415],
            'DEh'  => [0, 0, 0, 0.131, 0.136, 0.352],
            'DEdt' => [0, 0, 0.178, 0, 0.342, 0.257],
            'DEd'  => [0, 0, 0.178, 0, 0.263, 0.257],
        ];
        
        $result = [
            'Portero' => [
                'PO'   => null,
            ],
            'Defensa' => [
                'DL'   => null,
                'DLo'  => null,
                'DLh'  => null,
                'DLd'  => null,
                'DC'   => null,
                'DCo'  => null,
                'DCh'  => null,
            ],
            'Medio' => [
                'MC'   => null,
                'MCo'  => null,
                'MCh'  => null,
                'MCd'  => null,
            ],
            'Lateral' => [
                'EX'   => null,
                'EXo'  => null,
                'EXh'  => null,
                'EXd'  => null,
            ],
            'Delantero' => [
                'DE'   => null,
                'DEh'  => null,
                'DEdt' => null,
                'DEd'  => null,
            ]
        ];

        foreach ($result as $parent_key => &$parent_value) {
            foreach ($parent_value as $key => &$value) {
                $value = $this->getPositionStars($key, $table4, $table2);
            }
        }

        $result = collect($result)->map(function($result) {
            return collect($result)->max();
        });
            
        return $result;
    }

    private function getPositionStars($position, $table4, $table2) 
    {
        $res = 0;
        $res += $table4[$position][0]*$table2['porteria'];
        $res += $table4[$position][1]*$table2['defensa'];
        $res += $table4[$position][2]*$table2['jugadas'];
        $res += $table4[$position][3]*$table2['lateral'];
        $res += $table4[$position][4]*$table2['pases'];
        $res += $table4[$position][5]*$table2['anotacion'];
        return round($res,2);
        return floor($res*2)/2;
    }

    private $skills =[
        20 => 'divino',
        19 => 'utópico',
        18 => 'mágico',
        17 => 'mítico',
        16 => 'extraterrestre',
        15 => 'titánico',
        14 => 'sobrenatural',
        13 => 'clase mundial',
        12 => 'magnífico',
        11 => 'brillante',
        10 => 'destacado',
        9 => 'formidable',
        8 => 'excelente',
        7 => 'bueno',
        6 => 'aceptable',
        5 => 'insuficiente',
        4 => 'débil',
        3 => 'pobre',
        2 => 'horrible',
        1 => 'desastroso',
        0 => 'nulo',
    ];

    private function convertSkillsToNumbers($player) 
    {
        $convert = ['forma', 'condicion', 'experiencia', 'skills', 'liderazgo'];
        foreach ($convert as $name) {
            $player = $this->convertSkillToNumber($player, $name);
        }
        return $player;
    }

    private function convertSkillToNumber($player, $value) 
    {
        if(!isset($player[$value]) || is_numeric($player[$value]))
            return $player;
        if(is_array($player[$value])) {
            foreach ($player[$value] as $key => &$name) {
                if(is_numeric($name))
                    continue;
                $number = collect($this->skills)->search($name);
                $name = $number;
            }
            return $player;
        }
        $name = $player[$value];
        $number = collect($this->skills)->search($name);
        $player[$value] = $number;
        return $player;
    }

    public function getHTMS($player) 
    {
        $player = $this->convertSkillsToNumbers($player);
        // training points per week at a certain age
        $skills = $player['skills'];

        $pointsAge = [];
        $pointsAge[17] = 10;
        $pointsAge[18] = 9.92;
        $pointsAge[19] = 9.81;
        $pointsAge[20] = 9.69;
        $pointsAge[21] = 9.54;
        $pointsAge[22] = 9.39;
        $pointsAge[23] = 9.22;
        $pointsAge[24] = 9.04;
        $pointsAge[25] = 8.85;
        $pointsAge[26] = 8.66;
        $pointsAge[27] = 8.47;
        $pointsAge[28] = 8.27;
        $pointsAge[29] = 8.07;
        $pointsAge[30] = 7.87;
        $pointsAge[31] = 7.67;
        $pointsAge[32] = 7.47;
        $pointsAge[33] = 7.27;
        $pointsAge[34] = 7.07;
        $pointsAge[35] = 6.87;
        $pointsAge[36] = 6.67;
        $pointsAge[37] = 6.47;
        $pointsAge[38] = 6.26;
        $pointsAge[39] = 6.06;
        $pointsAge[40] = 5.86;
        $pointsAge[41] = 5.65;
        $pointsAge[42] = 6.45;
        $pointsAge[43] = 6.24;
        $pointsAge[44] = 6.04;
        $pointsAge[45] = 5.83;

        // keeper, defending, playmaking, winger, passing, scoring, setPieces
        $pointsSkills = [];
        $pointsSkills[0] = [0, 0, 0, 0, 0, 0, 0];
        $pointsSkills[1] = [2, 4, 4, 2, 3, 4, 1];
        $pointsSkills[2] = [12, 18, 17, 12, 14, 17, 2];
        $pointsSkills[3] = [23, 39, 34, 25, 31, 36, 5];
        $pointsSkills[4] = [39, 65, 57, 41, 51, 59, 9];
        $pointsSkills[5] = [56, 98, 84, 60, 75, 88, 15];
        $pointsSkills[6] = [76, 134, 114, 81, 104, 119, 21];
        $pointsSkills[7] = [99, 175, 150, 105, 137, 156, 28];
        $pointsSkills[8] = [123, 221, 190, 132, 173, 197, 37];
        $pointsSkills[9] = [150, 271, 231, 161, 213, 240, 46];
        $pointsSkills[10] = [183, 330, 281, 195, 259, 291, 56];
        $pointsSkills[11] = [222, 401, 341, 238, 315, 354, 68];
        $pointsSkills[12] = [268, 484, 412, 287, 381, 427, 81];
        $pointsSkills[13] = [321, 580, 493, 344, 457, 511, 95];
        $pointsSkills[14] = [380, 689, 584, 407, 540, 607, 112];
        $pointsSkills[15] = [446, 809, 685, 478, 634, 713, 131];
        $pointsSkills[16] = [519, 942, 798, 555, 738, 830, 153];
        $pointsSkills[17] = [600, 1092, 924, 642, 854, 961, 179];
        $pointsSkills[18] = [691, 1268, 1070, 741, 988, 1114, 210];
        $pointsSkills[19] = [797, 1487, 1247, 855, 1148, 1300, 246];
        $pointsSkills[20] = [924, 1791, 1480, 995, 1355, 1547, 287];
        $pointsSkills[21] = [1074, 1791, 1791, 1172, 1355, 1547, 334];
        $pointsSkills[22] = [1278, 1791, 1791, 1360, 1355, 1547, 388];
        $pointsSkills[23] = [1278, 1791, 1791, 1360, 1355, 1547, 450];

        $actValue = $pointsSkills[floor($skills['porteria'])][0];
        $actValue += $pointsSkills[floor($skills['defensa'])][1];
        $actValue += $pointsSkills[floor($skills['jugadas'])][2];
        $actValue += $pointsSkills[floor($skills['lateral'])][3];
        $actValue += $pointsSkills[floor($skills['pases'])][4];
        $actValue += $pointsSkills[floor($skills['anotacion'])][5];
        $actValue += $pointsSkills[floor($skills['balon_parado'])][6];

        // now calculating the potential at 28yo
        $age_limit = 28;
        $weeks_in_season = 16;
        $days_in_week = 7;
        $days_in_season = $days_in_week * $weeks_in_season;

        $points_diff = 0;
        if ($player['age'] < $age_limit) {
            // add weeks to reach next birthday (112 days)
            $pointsYears = $pointsAge[$player['age']];
            $points_diff = ($days_in_season - $player['days']) / $days_in_week * $pointsYears;
            // adding 16 weeks per whole year until 28 y.o.
            for ($i = $player['age'] + 1; $i < $age_limit; $i++) {
                $points_diff += $weeks_in_season * $pointsAge[$i];
            }
        }
        else {
            // subtract weeks to previous birthday
            $points_diff = $player['days'] / $days_in_week * $pointsAge[$player['age']];
            // subtracting 16 weeks per whole year until 28
            for ($i = $player['age']; $i > $age_limit; $i--) {
                $points_diff += $weeks_in_season * $pointsAge[$i];
            }
            $points_diff = -$points_diff;
        }
        $potValue = $actValue + $points_diff;

        return [
            'habilidad' => $actValue, 
            'potencial' => round($potValue)
        ];
    }
}
