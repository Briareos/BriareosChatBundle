<?php

namespace Briareos\ChatBundle\Controller;

/*
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;
use App\UserBundle\Entity\User;
use App\NodejsBundle\Entity\ChatCache;
use App\NodejsBundle\Entity\ChatUser;
use App\NodejsBundle\Entity\ChatMessage;
use App\NodejsBundle\Util\Nodejs;
use App\NodejsBundle\Util\NodejsMessage;
*/

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\Security\Core\User\UserInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

class ActivateController extends ContainerAware
{
    public function getUser()
    {
        if (!$this->container->has('security.context')) {
            throw new \LogicException('The SecurityBundle is not registered in your application.');
        }

        if (null === $token = $this->container->get('security.context')->getToken()) {
            return null;
        }

        if (!is_object($user = $token->getUser())) {
            return null;
        }

        return $user;
    }

    /**
     * @Route("/chat/activate", name="briareos_chat_activate")
     */
    public function activateAction()
    {
        /** @var $sender UserInterface */
        $sender = $this->getUser();

        /** @var $userManager \App\UserBundle\Entity\UserManager */
        $userManager = $this->get('user_manager');

        $receiverId = $this->getRequest()->request->get('uid');

        $tid = $this->container->get('request')->request->get('tid', '');

        if (empty($tid) || !is_string($tid)) {
            throw new AccessDeniedException('Parameter "tid" is required and must be a string.');
        }

        if (!$sender instanceof UserInterface) {
            throw new AccessDeniedException('User must be logged in to use chat.');
        }

        if (!$sender->getChatCache() instanceof ChatCache) {
            throw new AccessDeniedException('User doesn\'t have an assigned ChatCache entity.');
        }

        if ($receiverId) {
            /** @var $receiver User */
            $receiver = $userManager->find($receiverId);
            if (!$receiver instanceof UserInterface || $sender->isEqualTo($receiver)) {
                throw new AccessDeniedException();
            }
        } else {
            $receiver = null;
        }

        if ($receiver) {
            if ($this->generateChatUser($sender, $receiver)) {
                $this->generateChatUser($receiver, $sender);
            }
        }

        $sender->getChatCache()->setActive($receiver);
        if ($receiver) {
            $sender->getChatCache()->addOpen($receiver->getId());
        }
        /** @var $em \Doctrine\ORM\EntityManager */
        $em = $this->getDoctrine()->getManager();
        $em->persist($sender->getChatCache());

        if ($receiver) {
            $lastMessage = $em->getRepository('App\NodejsBundle\Entity\ChatMessage')->findBy(array(
                'sender' => $receiver,
                'receiver' => $sender,
            ), array(
                'id' => 'desc',
            ), 1);
            if ($lastMessage) {
                $chatUser = $em->getRepository('App\NodejsBundle\Entity\ChatUser')->findOneBy(array(
                    'sender' => $receiver,
                    'receiver' => $sender,
                ));
                $chatUser->setLast($lastMessage[0]);
                $em->persist($chatUser);
            }
        }

        /** @var $nodejs Nodejs */
        $nodejs = $this->get('nodejs');
        $activateMessage = new NodejsMessage('chat');
        $activateMessage->setChannel('nodejs_user_' . $sender->getId());
        if ($receiver) {
            $data = array(
                'command' => 'activate',
                'tid' => $tid,
                'uid' => $receiver->getId(),
                'd' => array(
                    'u' => $receiver->getId(),
                    'n' => $receiver->getName(),
                    'p' => 'http://loopj.com/images/facebook_32.png',
                ),
            );
        } else {
            $data = array(
                'command' => 'activate',
                'tid' => $tid,
                'uid' => 0,
            );
        }
        $activateMessage->setData($data);
        $nodejs->send($activateMessage);

        $em->flush();
        return new Response();
    }

    public function generateChatUser(User $sender, User $receiver)
    {
        /** @var $em \Doctrine\ORM\EntityManager */
        $em = $this->getDoctrine()->getManager();
        $chatUserRepository = $em->getRepository('App\NodejsBundle\Entity\ChatUser');
        $chatUser = $chatUserRepository->findOneBy(array(
            'sender' => $sender,
            'receiver' => $receiver,
        ));

        if ($chatUser) {
            return false;
        } else {
            $chatUser = new ChatUser();
            $chatUser->setSender($sender);
            $chatUser->setReceiver($receiver);
            $em->persist($chatUser);
            return true;
        }
    }
}