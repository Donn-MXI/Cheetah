<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Http;

class ApiController extends Controller
{

    public function endpoint1(){

        $response = Http::get('https://hacker-news.firebaseio.com/v0/newstories.json?print=pretty');


        if($response->successful() == true){

            $items = array();
            $items = json_decode($response->body());
            arsort($items);

            $titlesArr =  $this->sortTitles(array_slice($items, 0, 25), 'endpoint1');
            $words = array_count_values(str_word_count($titlesArr, 1));
            arsort($words);

            return  response()->json(['count_of_words'=> array_slice($words, 0, 10), 'titles' => $titlesArr]);
        }



    }




    public function sortTitles($items, $from){

        $client = new Client(['base_uri' => 'https://hacker-news.firebaseio.com/v0/']);
        $requests = function ($items) {
            foreach($items as $project){
                yield new Psr7Request('GET', "item/".$project.".json?print=pretty");
            }
        };
        $titles = '';
        if($items == 'endpoint1'){
            $pool = new Pool($client, $requests($items),[
                'concurrency' => 25,
                'fulfilled' => function (Response $response, $index) use(&$titles) {
                    $news = json_decode($response->getBody());
                    $titles = $titles.', '.$news->title;
                    return response($titles);
                },
                'rejected' => function (RequestException $reason, $index) {
                    return response()->json($reason);
                },
            ]);

            $pool->promise()->wait();

        }else{
            //endpoint 2
            $now = Carbon::now();
            $weekStartDate = $now->startOfWeek()->format('Y-m-d');
            $weekEndDate = $now->endOfWeek()->format('Y-m-d');

            $pool = new Pool($client, $requests($items),[
                'concurrency' => 500,
                'fulfilled' => function (Response $response, $index) use(&$titles, &$weekStartDate, &$weekEndDate) {
                    $news = json_decode($response->getBody());
                    $datetimeFormat = 'Y-m-d';
                    $date = date($datetimeFormat,  $news->time);
                    if( $date >= $weekStartDate  && $date <= $weekEndDate){
                        $titles = $titles.', '.$news->title;
                        return response($titles);
                    }
                },
                'rejected' => function (RequestException $reason, $index) {
                    return response()->json($reason);
                },
            ]);
            $pool->promise()->wait();
        }
        return $titles;
    }




    public function endpoint2(){
        $response = Http::get('https://hacker-news.firebaseio.com/v0/newstories.json?print=pretty');

        if($response->successful() == true){

            $items = array();
            $items = json_decode($response->body());
            arsort($items);
            $titlesArr =  $this->sortTitles($items, 'Endpoint2');
            $words = array_count_values(str_word_count($titlesArr, 1));
            arsort($words);
            return  response()->json(['count_of_words'=> array_slice($words, 0, 10), 'titles' => $titlesArr]);
        }
    }


    public function endpoint3(){

        $response = Http::get('https://hacker-news.firebaseio.com/v0/newstories.json?print=pretty');

        if($response->successful() == true){

            $items = array();
            $items = json_decode($response->body());
            arsort($items);

            $fetched_news =  $this->getListForEndpoint3($items);

          //  $titlesArr = $this->checkKarma($fetched_news);

            $words = array_count_values(str_word_count($fetched_news, 1));
            arsort($words);
            return  response()->json(['count_of_words'=> array_slice($words, 0, 10), 'titles' => $fetched_news]);
        }
    }




    //fetch user data and see if the karma is > 10.0000
    public function checkKarma($items){
        ini_set('max_execution_time', 180);
        $client = new Client(['base_uri' => 'https://hacker-news.firebaseio.com/v0/']);
        $requests = function ($items) {
            foreach($items as $project){

                yield new Psr7Request('GET', "user/".$project->by.".json?print=pretty");

            }
        };
        $titles = '';
        $users = [];

        $pool = new Pool($client, $requests($items),[
            'concurrency' => 600,
            'fulfilled' => function (Response $response, $index) use(&$users) {
                $user = json_decode($response->getBody());
                if($user->karma > 10.000){
                    array_push($users, $user->id);
                    return response($users);
                }
            },
            'rejected' => function (RequestException $reason, $index) {
                return response()->json($reason->getMessage());
            },
        ]);

        $pool->promise()->wait();
        foreach($items as $item ){
            if(in_array($item->by, $users)){
                $titles = $titles.', '.$item->title;
            }
        }
        return $titles;
    }


    public function getListForEndpoint3($items){
        ini_set('max_execution_time', 180);

        $client = new Client(['base_uri' => 'https://hacker-news.firebaseio.com/v0/']);
        $requests = function ($items) {
            foreach($items as $project){
                yield new Psr7Request('GET', "item/".$project.".json?print=pretty");
            }
        };
        $titles = [];

        $pool = new Pool($client, $requests($items),[
            'concurrency' => 600,
            'fulfilled' => function (Response $response, $index) use(&$titles) {
                $news = json_decode($response->getBody());
                array_push($titles, $news);
                return response($titles);
            },
            'rejected' => function (RequestException $reason, $index) {
                return response()->json($reason->getMessage());
            },
        ]);

        $pool->promise()->wait();
        $res = $this->checkKarma($titles);

        return $res;
    }





}
