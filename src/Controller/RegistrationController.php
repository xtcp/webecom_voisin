<?php
/**
 *   RegistrationController.php 
 *      Controleur de registre de compte d'utilisateur
 * 
 *      Role:
 *          Préparer l'affichage de la page de creation de compte de l'utilisateur,
 *              traite la creation de compte et la redirection vers le formulaire ou la page d'accueil si sucés
 */
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

use Doctrine\ORM\EntityManagerInterface;

use App\Entity\User;
use App\Form\RegistrationFormType;

use App\Service\ImageUploader;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class RegistrationController extends AbstractController
{
    /** 
     *  Rôle :
     *     Préparer l'affichage de la page de creation de compte de l'utilisateur
     *
     *  Paramètres :
     *      form - Les données de requête du formulaire envoyés (email, password, agreeTerms, image)
     * 
     *  Retour :
     *     Response - Template de la page de création d'utilisateur ou espace mon compte si succés
     */
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager, #[Autowire(service: 'user_image_uploader')] ImageUploader $imageUploader): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $motdepasse */
            $motdepasse = $form->get('password')->getData();
            $image = $form->get('image')->getData();
            if ($image) {
                $user->setImage($imageUploader->upload($image));
            }
            // hasher le mot de passe
            $user->setPassword($userPasswordHasher->hashPassword($user, $motdepasse));

            $entityManager->persist($user);
            $entityManager->flush();

            return $this->redirectToRoute('app_index');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
