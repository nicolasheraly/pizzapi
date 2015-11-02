<?php

namespace Pizzapi\CoreBundle\Controller;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{
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

        try {
            $res = $client->request('GET', $apiUrl . "/pizzas");
            $pizzaList = json_decode($res->getBody()->getContents(), true);

            foreach ($pizzaList as &$pizza) {
                $res = $client->request('GET', $apiUrl . '/orders/' . $pizza['id']);
                $currentPizza = json_decode($res->getBody()->getContents(), true);

                $pizza['status'] = $currentPizza['status'];
            }
        } catch (ClientException $e) {
        }

        return $this->render('PizzapiCoreBundle:Default:index.html.twig', array('pizzas' => $pizzaList));
    }

    public function orderAction(Request $request, $id)
    {
        $apiUrl = $this->getParameter("api_url");
        $client = new Client();

        try {
            $res = $client->request('POST', $apiUrl . '/orders', [
                'json' => ['id' => (int)$id]
            ]);

            $this->addFlash(
                'success',
                'Votre commande a bien été passée !'
            );

            return $this->redirect($this->generateUrl('pizzapi_core_homepage'));
        } catch (ClientException $e) {
            var_dump($e->getMessage()); die;
        }
    }
}
