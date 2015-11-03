<?php

namespace Pizzapi\CoreBundle\Command;

use GuzzleHttp\Client;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateRedisCacheCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('redis:updateCache')
            ->setDescription('Update redis cache.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $apiUrl = $this->getContainer()->getParameter('api_url');
        $redis = $this->getContainer()->get('snc_redis.cache');

        try {
            $redis->set('pizzas', null);
            $client = new Client();
            $res = $client->request('GET', $apiUrl . "/pizzas");
            $pizzaList = json_decode($res->getBody()->getContents(), true);

            $redis->append('pizzas', json_encode($pizzaList));
            $output->writeln("Update redis cache completed !");
        } catch (\Exception $e) {
            var_dump($e->getMessage()); die;

        }
    }
}
