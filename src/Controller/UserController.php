<?php
/**
 *   UserController.php 
 *      Controleur d'utilisateur (profil, demandes d'ami)
 * 
 *      Role:
 *          Préparer l'affichage de la page de profil d'un utilisateur, publications et amis
*             et la modification d'utilisateur et demandes d'amie
 */
namespace App\Controller;

use App\Entity\FriendRequest;
use App\Entity\User;
use App\Form\ProfileType;
use App\Repository\FriendRequestRepository;
use App\Repository\PostRepository;
use App\Repository\UserRepository;
use App\Service\ImageUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class UserController extends AbstractController
{
    /** 
     *  Rôle :
     *     Préparer l'affichage de la page de profil d'un utilisateur, publications et amis
     *           et la modification d'utilisateur et demandes d'amie
     *
     *  Paramètres :
     *      id - L'id de l'utilisateur
     *      form - Les données de requête du formulaire envoyés (email, password, agreeTerms)
     * 
     *  Retour :
     *     Response - Template de la page de création d'utilisateur ou espace mon compte si succés
     */
    #[Route('/profil/{id}', name: 'app_user_profile', requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function profile(int $id, PostRepository $postRepository, UserRepository $userRepository): Response
    {
        $user = $userRepository->find($id);

        if ($user === null) {
            throw $this->createNotFoundException("Ce membre n'existe pas.");
        }

        /** @var User $viewer */
        $viewer = $this->getUser();
        $isOwner = $viewer->getId() === $user->getId();
        $isFriend = $viewer->isFriendWith($user);

        $posts = $postRepository->findVisibleForProfile($user, $isFriend, $isOwner);

        return $this->render('user/profile.html.twig', [
            'user' => $user,
            'posts' => $posts,
            'isOwner' => $isOwner,
            'isFriend' => $isFriend,
        ]);
    }
    /** 
     *  Rôle :
     *     Traiter la modification d'un utilisateur
     *
     *  Paramètres :
     *      id - L'id de l'utilisateur
     *      form - Les données de requête du formulaire envoyés (email, password, username, image, bio)
     * 
     *  Retour :
     *     Response - Template de la page de modification d'utilisateur
     */
    #[Route('/profil/{id}/modifier', name: 'app_user_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function edit(User $user, Request $request, EntityManagerInterface $em, #[Autowire(service: 'user_image_uploader')] ImageUploader $imageUploader): Response
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        if ($currentUser === null || $user->getId() !== $currentUser->getId()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                dump($error->getMessage(), $error->getOrigin()?->getName());
            }
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile|null $image */
            $image = $form->get('image')->getData();
            
            if ($image) {
                $user->setImage($imageUploader->replace($image, $user->getImage()));
            }
            $em->flush();

            $this->addFlash('success', 'Profil mis à jour.');
            return $this->redirectToRoute('app_user_profile', ['id' => $user->getId()]);
        }

        return $this->render('user/edit.html.twig', ['form' => $form]);
    }
    /** 
     *  Rôle :
     *     Traiter une demande d'amitie et redirectione vers sa page profil
     *
     *  Paramètres :
     *      id - L'id de la demande
     *      receiver - L'utilisateur a ajouter en ami (qui reçoit la demande)
     *      (sender = Utilisateur connectée)
     * 
     *  Retour :
     *     Response - Template de la page de profil de l'utilisateur demandé
     */
    #[Route('/amis/demander/{id}', name: 'app_friend_request_send', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function sendFriendRequest(User $receiver, Request $request, FriendRequestRepository $frRepo, EntityManagerInterface $em): Response
    {
        /** @var User $sender */
        $sender = $this->getUser();

        if ($sender->getId() === $receiver->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas vous ajouter vous-même.');
            return $this->redirectToRoute('app_user_profile', ['id' => $receiver->getId()]);
        }

        if (!$this->isCsrfTokenValid('friend-request-' . $receiver->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $existing = $frRepo->findBetween($sender, $receiver);
        if ($existing === null) {
            $fr = new FriendRequest();
            $fr->setSender($sender);
            $fr->setReceiver($receiver);
            $fr->setStatus(FriendRequest::STATUS_PENDING);
            $em->persist($fr);
            $em->flush();
            $this->addFlash('success', "Demande d'amitié envoyée.");
        }

        return $this->redirectToRoute('app_user_profile', ['id' => $receiver->getId()]);
    }

    /** 
     *  Rôle :
     *     Preparer l'affichage de la liste de demande d'amis
     * 
     *  Retour :
     *     Response - Template de la page de demandes d'ami
     */
    #[Route('/amis/demandes', name: 'app_friend_requests')]
    #[IsGranted('ROLE_USER')]
    public function friendRequests(FriendRequestRepository $frRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('user/friend_requests.html.twig', [
            'requests' => $frRepo->findPendingReceivedBy($user),
        ]);
    }

    /** 
     *  Rôle :
     *     Traiter l'action d'accepter une demande d'ami
     *
     *  Paramètres :
     *      id - L'id de la demande
     *      friendRequest - La requête d'ami qui contient l'id des utilisateurs
     * 
     *  Retour :
     *     Response - Template de la page de demandes d'ami
     */
    #[Route('/amis/demandes/{id}/accepter', name: 'app_friend_request_accept', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function acceptFriendRequest(FriendRequest $friendRequest, Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($friendRequest->getReceiver()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }
        if ($this->isCsrfTokenValid('friend-response-' . $friendRequest->getId(), $request->request->get('_token'))) {
            $friendRequest->setStatus(FriendRequest::STATUS_ACCEPTED);
            $em->flush();
            $this->addFlash('success', 'Demande acceptée.');
        }

        return $this->redirectToRoute('app_friend_requests');
    }
    /** 
     *  Rôle :
     *     Traiter l'action de refuser une demande d'ami
     *
     *  Paramètres :
     *      id - L'id de la demande
     *      friendRequest - La requête d'ami qui contient l'id des utilisateurs
     * 
     *  Retour :
     *     Response - Template de la page de demandes d'ami
     */
    #[Route('/amis/demandes/{id}/refuser', name: 'app_friend_request_refuse', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function refuseFriendRequest(FriendRequest $friendRequest, Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($friendRequest->getReceiver()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }
        if ($this->isCsrfTokenValid('friend-response-' . $friendRequest->getId(), $request->request->get('_token'))) {
            $friendRequest->setStatus(FriendRequest::STATUS_REFUSED);
            $em->flush();
            $this->addFlash('info', 'Demande refusée.');
        }

        return $this->redirectToRoute('app_friend_requests');
    }

    /** 
     *  Rôle :
     *     Prepare l'affichage de la page de la liste d'amis
     *
     *  Retour :
     *     Response - Template de la page de la liste d'amis
     */
    #[Route('/amis', name: 'app_friend_list')]
    #[IsGranted('ROLE_USER')]
    public function friendList(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('user/friend_list.html.twig', [
            'friends' => $user->getFriends(),
        ]);
    }

}
