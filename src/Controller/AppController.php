<?php
/**
 *   AppController.php 
 *      Controleur de l'application
 * 
 *      Role:
 *          Préparer l'affichage de la page principal
 */
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use App\Repository\PostRepository;

final class AppController extends AbstractController
{
    /** 
     *  Rôle :
     *     Préparer l'affichage de la page principal
     *
     *  Retour :
     *     Response - Template de la page principale avec les dernieres publications publiques
     */
    #[Route('/', name: 'app_index')]
    public function index(PostRepository $postRepository): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_feed');
        }

        $latestPublicPosts = $postRepository->findForFeed([], 'public');

        return $this->render('app/index.html.twig', [
            'latestPublicPosts' => $latestPublicPosts,
        ]);
    }
}
