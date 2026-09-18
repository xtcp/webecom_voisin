<?php
/**
 *   OnlineController.php 
 *      Subscriber Symfony (ecouteur d'evenements) pour mettre a jour le status online d'un utilisateur
 *          
 * 
 *      Role:
 *          Ecoute pour les evenements login/logout pour mettre à jour le status en ligne ou hors ligne
 *              de l'utilisateur
 */

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 *  OnlineSubscriber
 *
 *      Role:
 *          Mettre à jour le statut "en ligne" (User::$online) d'un utilisateur
 *          lors de sa connexion et de sa déconnexion.
 */
class OnlineSubscriber implements EventSubscriberInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }
    /** 
     *  Rôle :
     *     Les "binds"/liaisons des ecouteurs d'evenement
     *
     *  Retour :
     *     La liste avec les methodes associés à la classe de l'evenement
     */
    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LogoutEvent::class => 'onLogout',
        ];
    }

    /** 
     *  Rôle :
     *     Methode executée à l'execution de l'evenement LoginSuccessEvent
     *      Verifie si l'utilisateur existe et s'il existe, mettre le status comme en ligne
     */
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $user->setOnline(true);
        $this->em->flush();
    }

    /** 
     *  Rôle :
     *     Methode executée à l'execution de l'evenement LogoutEvent
     *      Verifie si l'utilisateur existe et s'il existe, mettre le status comme hors ligne
     */
    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        $user = $token?->getUser();

        if (!$user instanceof User) {
            return;
        }

        $user->setOnline(false);
        $this->em->flush();
    }
}