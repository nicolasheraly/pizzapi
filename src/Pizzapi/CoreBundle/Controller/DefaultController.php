<?php

namespace Pizzapi\CoreBundle\Controller;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
            $res = $client->request('GET', $apiUrl . "/pizzas", [
                'timeout' => 5
            ]);
            $pizzaList = json_decode($res->getBody()->getContents(), true);
        } catch (ClientException $e) {
            var_dump($e->getMessage());
            die;
        } catch (\Exception $e) {
            $content = $this->render('TwigBundle:Exception:error404.html.twig');

            return new Response($content, 404, array('Content-Type', 'text/html'));
        }

        return $this->render('PizzapiCoreBundle:Default:index.html.twig', array('pizzas' => $pizzaList));
    }

    public function orderAction(Request $request, $id)
    {
        $apiUrl = $this->getParameter("api_url");
        $client = new Client();

        try {
            $res = $client->request('POST', $apiUrl . '/orders', [
                'json'      => ['id' => (int)$id],
                'timeout' => 5
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
            $content = $this->render('TwigBundle:Exception:error404.html.twig');

            return new Response($content, 404, array('Content-Type', 'text/html'));
        }
    }
}
