<?php

namespace Pizzapi\CoreBundle\Controller;

use Ejsmont\CircuitBreaker\Factory;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{
    private $redis;

    /**
     * List pizza.
     *
     * @param $name
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction()
    {
        $client = new Client();
        $apiUrl = $this->getParameter("api_url");

        $pizzaList = json_decode($this->getRedisInstance()->get('pizzas'), true);

        return $this->render('PizzapiCoreBundle:Default:index.html.twig', array(
            'pizzas'    => $pizzaList,
            'available' => $this->getBreaker()->isAvailable('order')
        ));
    }

    public function orderAction(Request $request, $id)
    {
        $apiUrl = $this->getParameter("api_url");
        $client = new Client();

        if ($this->getBreaker()->isAvailable('order')) {
            try {
                $res = $client->request('POST', $apiUrl . '/orders', [
                    'json'    => ['id' => (int) $id],
                    'timeout' => 2
                ]);
                $command = json_decode($res->getBody()->getContents(), true);

                $this->addFlash(
                    'success',
                    "Votre commande ". $command['id'] ." a bien été passée !"
                );

                return $this->redirect($this->generateUrl('pizzapi_core_homepage'));
            } catch (ClientException $e) {
                var_dump($e->getMessage()); die;
            } catch (\Exception $e) {
                $this->getBreaker()->reportFailure('order');
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
