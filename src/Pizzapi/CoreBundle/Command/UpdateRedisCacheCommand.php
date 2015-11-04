<?php

namespace Pizzapi\CoreBundle\Command;

use GuzzleHttp\Client;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Metrics\Client as LibratoClient;
use GuzzleHttp\TransferStats;

class UpdateRedisCacheCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('redis:updateCache')
            ->setDescription('Update redis cache.')
        ;
    }
    protected $responseStats;

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $apiUrl = $this->getContainer()->getParameter('api_url');
        $redis = $this->getContainer()->get('snc_redis.cache');

        try {

            $client = new Client();
            $res = $client->request('GET', $apiUrl . "/pizzas",[
                'source' => 'get/pizzas',
                'on_stats' => function (TransferStats $stats) {

                    if ($stats->hasResponse()) {
                        $client = new LibratoClient($this->getContainer()->getParameter("librato_email"), $this->getContainer()->getParameter("librato_token"));
                        $client->post('/metrics', array(
                            'gauges' => array(
                                array('name' => 'ResponseTime', 'value' => $stats->getTransferTime(), 'source' => 'get/pizzas'),
                                array('name' => 'ResponseStatusCode', 'value' => 1, $stats->getResponse()->getStatusCode(), 'source' => 'get/pizzas'),
                                array('name' => 'ApiRequested', 'value' => 1, 'source' => 'get/pizzas')
                            )
                        ));

                    }
              }]);


            $pizzaList = json_decode($res->getBody()->getContents(), true);
            $redis->set('pizzas', json_encode($pizzaList));
            $output->writeln("Update redis cache completed !");
        } catch (\Exception $e) {
            var_dump($e->getMessage()); die;
        }
    }
}
