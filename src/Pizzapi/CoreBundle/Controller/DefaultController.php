<?php

namespace Pizzapi\CoreBundle\Controller;

use Ejsmont\CircuitBreaker\Factory;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\TransferStats;
use Metrics\Client as LibratoClient;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{
    private $redis;

    /**
     * List pizza.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction()
    {
        $pizzaList = json_decode($this->getRedisInstance()->get('pizzas'), true);

        return $this->render('PizzapiCoreBundle:Default:index.html.twig', array(
            'pizzas'    => $pizzaList,
            'available' => $this->getBreaker()->isAvailable('order')
        ));
    }

    /**
     * Order a pizza.
     *
     * @param Request $request
     * @param $id The pizza id.
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function orderAction(Request $request, $id)
    {
        $apiUrl = $this->getParameter("api_url");
        $client = new Client();
        $container = $this->container;
        if ($this->getBreaker()->isAvailable('order')) {
            $LibratoClient = new LibratoClient($container->getParameter("librato_email"), $container->getParameter("librato_token"));
            $LibratoClient->post('/metrics', array(
                'gauges' => array(
                    array('name' => 'CircuitBreaker', 'value' => 0)
                )
            ));
            try {
                $res = $client->request('POST', $apiUrl . '/orders', [
                    'json'    => ['id' => (int) $id],
                    'timeout' => 30,
                    'on_stats' => function (TransferStats $stats) use ($container)  {

                        if ($stats->hasResponse()) {
                            $client = new LibratoClient($container->getParameter("librato_email"), $container->getParameter("librato_token"));
                            $client->post('/metrics', array(
                                'gauges' => array(
                                    array('name' => 'ResponseTime', 'value' => $stats->getTransferTime()),
                                    array('name' => 'ResponseStatusCode', 'value' => $stats->getResponse()->getStatusCode()),
                                     array('name' => 'ApiRequested', 'value' => 1)
                                )
                            ));

                        }
                    }]);
                $command = json_decode($res->getBody()->getContents(), true);

                $this->addFlash(
                    'success',
                    "Votre commande ". $command['id'] ." a bien été passée !"
                );

                return $this->redirect($this->generateUrl('pizzapi_core_homepage'));
            } catch (\Exception $e) {
                $this->getBreaker()->reportFailure('order');
                $LibratoClient = new LibratoClient($container->getParameter("librato_email"), $container->getParameter("librato_token"));
                $LibratoClient->post('/metrics', array(
                    'gauges' => array(
                        array('name' => 'CircuitBreaker', 'value' => 1)
                    )
                ));
                $content = $this->render('TwigBundle:Exception:error404.html.twig');

                return new Response($content, 404, array('Content-Type', 'text/html'));
            }
        }

        $content = $this->render('TwigBundle:Exception:error404.html.twig');

        return new Response($content, 404, array('Content-Type', 'text/html'));
    }

    /**
     * Return a new redis instance.
     *
     * @return \Redis
     */
    private function getRedisInstance()
    {
        if (is_null($this->redis)) {
            $this->redis = new \Redis();
            $this->redis->connect('localhost:6379');
        }

        return $this->redis;
    }

    /**
     * Get breaker instance.
     */
    private function getBreaker()
    {
        $breakerFactory = new Factory();

        return $breakerFactory->getRedisInstance($this->getRedisInstance(), 1, 10);
    }
}
