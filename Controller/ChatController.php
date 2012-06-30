<?php

namespace Briareos\ChatBundle\Controller;

use Symfony\Component\DependencyInjection\ContainerAware;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Briareos\ChatBundle\Entity\ChatSubjectInterface;
use Symfony\Component\HttpFoundation\Response;

class ChatController extends ContainerAware
{
    /**
     * Get a user from the Security Context
     *
     * @return mixed
     *
     * @throws \LogicException If SecurityBundle is not available
     *
     * @see Symfony\Component\Security\Core\Authentication\Token\TokenInterface::getUser()
     */
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
     * @Route("chat/cache", name="chat_cache")
     */
    public function cacheAction()
    {
        /** @var $subject ChatSubjectInterface */
        $subject = $this->getUser();

        if (!$subject instanceof ChatSubjectInterface) {
            throw new AccessDeniedException();
        }

        /*{
        * u : Subject's ID
        * n : Subject's name
        * p : Subject's picture
        * a : Active conversation's ID
        * v : Array of open conversation IDs
        * o : List of chat partners, indexed by ID
        *   {
        *     u : Partner's ID
        *     n : Partner's name
        *     p : Partner's picture
        *     s : Partner's status
        *   }
        * w : Chat conversations, indexed by ID
        *   {
        *   d : Partner's data
        *     {
        *       u : Partner's uid
        *       n : Partner's name
        *       p : Partner's picture
        *       s : Partner's status
        *     }
        *   m : Messages, indexed by ID
        *     {
        *       i : Message ID
        *       r : 1 if it's a received message, 0 otherwise
        *       t : Message time (UNIX timestamp)
        *       b : Message body
        *     }
        *     e : Number of new messages
        *   }
        * }
        */

        /** @var $em \Doctrine\ORM\EntityManager */
        $em = $this->container->get('doctrine.orm.default_entity_manager');

        $subjectRepository = $em->getRepository('BriareosChatBundle:ChatSubjectInterface');
        /** @var $stateRepository \Briareos\ChatBundle\Entity\ChatStateRepository */
        $stateRepository = $em->getRepository('BriareosChatBundle:ChatState');
        /** @var $chatState \Briareos\ChatBundle\Entity\ChatState */
        $chatState = $stateRepository->generateChatState($subject);

        $chatData = array(
            'u' => $subject->getId(),
            'n' => $subject->getChatName(),
            'p' => 'http://loopj.com/images/facebook_32.png',
            'a' => $chatState->getActiveConversationId(),
            'v' => $chatState->getOpenConversations(),
            //'o' => $this->getPresentUsers($user),
            'o' => array(),
            'w' => array(),
        );

        foreach ($chatData['v'] as $openConversationId) {
            $chatData['w'][$openConversationId] = array(
                'd' => array(),
                'm' => array(),
                'e' => 0
            );
        }

        /** @var $messageRepository \Briareos\ChatBundle\Entity\ChatMessageRepository */
        $messageRepository = $em->getRepository('BriareosChatBundle:ChatMessage');
        $messages = $messageRepository->getSubjectMessages($subject);
        foreach ($messages as $message) {
            if (!isset($chatData['w'][$message->partner_id])) {
                $chatData['w'][$message->partner_id] = array(
                    'd' => array(),
                    'm' => array(),
                    'e' => 0
                );
            }
            $messageText = $message->text;
            $chatData['w'][$message->partner_id]['m'][$message->id] = array(
                'i' => $message->id,
                'r' => $message->received,
                't' => strtotime($message->created),
                'b' => $messageText
            );
            if ($message->new) {
                $chatData['w'][$message->partner_id]['e']++;
            }
        }

        foreach ($chatData['w'] as $partnerId => &$window) {
            /* @TODO the partner resolving method should be refactored once a method to get repositories by interfaces
             * defined in external bundles is properly implemented. For now, the method is get p
             *
             */
            /** @var $partner ChatSubjectInterface */
            $partner = $subjectRepository->find($partnerId);
            $cacheKey = array_search($partnerId, $chatData['v']);
            if ($cacheKey === false) {
                $cache['v'][] = $partner->getId();
            }
            $window['d'] = array(
                'u' => $partner->getId(),
                'n' => $partner->getChatName(),
                'p' => 'http://loopj.com/images/facebook_32.png',
                's' => 1
            );
        }

        $openConversations = $chatState->getOpenConversations();
        $newConversations = array_diff($chatData['v'], $openConversations);
        if (!empty($newConversations)) {
            $newOpen = array_merge($openConversations, $newConversations);
            $chatState->setOpenConversations($newOpen);
            $em->flush($chatState);
        }

        $response = new Response(json_encode($chatData));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
}
