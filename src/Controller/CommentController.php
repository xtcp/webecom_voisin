<?php
/**
 *   CommentController.php 
 * 
 *      Controleur des commentaires des publications
 * 
 *      Role:
 *          Ajouter et effacer des commentaires des publications
 */
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Security\Http\Attribute\IsGranted;

use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\User;
use App\Form\CommentType;

final class CommentController extends AbstractController
{
    /** 
     *  Rôle :
     *     Ajouter un nouveau commentaire a une publication
     *
     *  Paramètres :
     *      id - L'id du post
     *      form - Les données de requête du formulaire envoyés (post, text)
     * 
     *  Retour :
     *     Response - Template de la page du fil d'actualité
     */
    #[Route('/publication/{id}/commentaire', name: 'app_comment_new', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function new(Post $post, Request $request, EntityManagerInterface $em): Response
    {


        $comment = new Comment();
        $comment->setPost($post);
        $comment->setUserId($this->getUser());
        $comment->setDatetime(new \DateTime());

        $form = $this->createForm(CommentType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($comment);
            $em->flush();
        }

        return $this->redirectToRoute('app_post_view', ['id' => $post->getId()]);
    }
    /** 
     *  Rôle :
     *     Traiter l'action d'effacer un commentaire
     *
     *  Paramètres :
     *      id - L'id du commentaire
     * 
     *  Retour :
     *     Response - Template de la page du fil d'actualité
     */
    #[Route('/commentaire/{id}/supprimer', name: 'app_comment_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function delete(Comment $comment, Request $request, EntityManagerInterface $em): Response
    {
        // Juste pour eviter que l'IDE se plaint
        /** @var User $user */
        $user = $this->getUser();
        $isOwner = $comment->getUserId()?->getId() === $user->getId();
        $isPostOwner = $comment->getPost()?->getUserId()?->getId() === $user->getId();

        if (!$isOwner && !$isPostOwner) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete-comment-' . $comment->getId(), $request->request->get('_token'))) {
            $em->remove($comment);
            $em->flush();
        }

        return $this->redirectToRoute('app_post_view', ['id' => $comment->getPost()->getId()]);
    }
}