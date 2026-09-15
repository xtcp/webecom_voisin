<?php
/**
 *   PostController.php 
 * 
 *      Controleur des publications
 * 
 *      Role:
 *          Afficher le fil d'actualité, creer, modifier, effacer, rechercher, liker une publication
 */
namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\User;
use App\Form\CommentType;
use App\Form\PostType;
use App\Repository\FriendRequestRepository;
use App\Repository\PostRepository;
use App\Service\ImageUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class PostController extends AbstractController
{
    /** 
     *  Rôle :
     *     Afficher le fil d'actualité de l'utilisateur connectée avec le filtre de ces publications, des amis ou publiques
     *
     *  Retour :
     *     Response - Template de la page du fil d'actualité avec les données des publications
     */
    #[Route('/fil-actualite', name: 'app_feed')]
    #[IsGranted('ROLE_USER')]
    public function feed(Request $request, PostRepository $postRepository, FriendRequestRepository $frRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $filter = $request->query->get('filtre', 'all');
        if (!in_array($filter, ['all', 'public', 'friends'], true)) {
            $filter = 'all';
        }

        $friends = $user->getFriends();
        $friendIds = array_map(static fn (User $u) => $u->getId(), $friends);
        
        $posts = $postRepository->findForFeed($friendIds, $filter, $user->getId());

        return $this->render('post/feed.html.twig', [
            'posts' => $posts,
            'filter' => $filter,
            'postForm' => $this->createForm(PostType::class, new Post())->createView(),
            'pendingRequestsCount' => count($frRepo->findPendingReceivedBy($user)),
            'onlineFriends' => array_filter($friends, static fn (User $f) => $f->isOnline()),
        ]);
    }
/** 
     *  Rôle :
     *     Traite l'action d'ajouter une nouvelle publication
     *
     *  Paramètres :
     *      form - Les données de requête du formulaire envoyés (image, text, visibilité)
     * 
     *  Retour :
     *     Response - Template de la page du fil d'actualité
     */
    #[Route('/publication/nouvelle', name: 'app_post_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $em, #[Autowire(service: 'post_image_uploader')] ImageUploader $imageUploader): Response
    {
        $post = new Post();
        $post->setUserId($this->getUser());

        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile|null $imageFile */
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $post->setImage($imageUploader->upload($imageFile));
            }

            $em->persist($post);
            $em->flush();

            $this->addFlash('success', 'Publication créée.');
            return $this->redirectToRoute('app_feed');
        }

        if ($request->isMethod('POST')) {
            // formulaire invalide : on revient au fil avec les erreurs
            return $this->render('post/feed.html.twig', [
                'posts' => [],
                'filter' => 'all',
                'postForm' => $form->createView(),
                'postFormHasErrors' => true,
            ]);
        }

        return $this->render('post/new.html.twig', [
            'postForm' => $form,
        ]);
    }
    /** 
     *  Rôle :
     *     Preparer l'affichage d'une publication
     *
     *  Paramètres :
     *      id - L'id de la publication
     * 
     *  Retour :
     *     Response - Template de la page d'affichage d'une publication
     */
    #[Route('/publication/{id}', name: 'app_post_view', requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function view(Post $post): Response
    {
        $comment = new Comment();
        $comment->setPost($post);
        return $this->render('post/view.html.twig', [
            'post' => $post,
            'commentForm' => $this->createForm(CommentType::class, $comment)->createView(),
        ]);
    }
    
    /** 
     *  Rôle :
     *     Traite l'action de modifier une publication
     *
     *  Paramètres :
     *      id - L'id de la publication
     *      form - Les données de requête du formulaire envoyés (image, text, visibilité)
     * 
     *  Retour :
     *     Response - Template de la page du fil d'actualité
     */
    #[Route('/publication/{id}/modifier', name: 'app_post_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function edit(Post $post, Request $request, EntityManagerInterface $em, #[Autowire(service: 'post_image_uploader')] ImageUploader $imageUploader): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($post->getUserId()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException("Vous ne pouvez modifier que vos propres publications.");
        }
        $form = $this->createForm(PostType::class, $post);
        $form->remove('imageFile');
        $form->add('imageFile', \Symfony\Component\Form\Extension\Core\Type\FileType::class, [
            'label' => 'Remplacer l’image (facultatif)',
            'mapped' => false,
            'required' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile|null $imageFile */
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $post->setImage($imageUploader->replace($imageFile, $post->getImage()));
            }
            $post->setDatetimeModification(new \DateTime());

            $em->flush();

            $this->addFlash('success', 'Publication modifiée.');
            return $this->redirectToRoute('app_feed');
        }

        return $this->render('post/edit.html.twig', [
            'postForm' => $form,
            'post' => $post,
        ]);
    }
    /** 
     *  Rôle :
     *     Traite l'action d'effacer une publication
     *
     *  Paramètres :
     *      id - L'id de la publication
     * 
     *  Retour :
     *     Response - Template de la page du fil d'actualité
     */
    #[Route('/publication/{id}/supprimer', name: 'app_post_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function delete(Post $post, Request $request, EntityManagerInterface $em, #[Autowire(service: 'post_image_uploader')] ImageUploader $imageUploader): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($post->getUserId()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException("Vous ne pouvez modifier que vos propres publications.");
        }
        if ($this->isCsrfTokenValid('delete-post-' . $post->getId(), $request->request->get('_token'))) {
            if ($post->getImage()) {
                $imageUploader->delete($post->getImage());
            }
            $em->remove($post);
            $em->flush();
            $this->addFlash('success', 'Publication supprimée.');
        }

        return $this->redirectToRoute('app_feed');
    }
    /** 
     *  Rôle :
     *     Traite l'action d'ajouter/effacer un like à une publication
     *
     *  Paramètres :
     *      id - L'id de la publication
     * 
     *  Retour :
     *     Response - Template de la page du fil d'actualité
     */
    #[Route('/publication/{id}/like', name: 'app_post_like', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function like(Post $post, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($post->isLikedByUser($user)) {
            $post->removeLikedBy($user);
        } else {
            $post->addLikedBy($user);
        }

        $em->flush();

        return $this->redirectToRoute('app_feed');
    }

}