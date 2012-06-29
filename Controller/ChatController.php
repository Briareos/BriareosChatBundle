<?php

namespace Briareos\ChatBundle\Controller;

use Symfony\Component\DependencyInjection\ContainerAware;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Briareos\ChatBundle\Entity\ChatSubjectInterface;

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

        /* {
        * u : This user's uid
        * n : This user's name
        * p : This user's picture
        * a : Active window ID
        * v : Array of open window IDs
        * o : List of online chat partners, indexed by uid
        *  {
        *   u : Partner's uid
        *   n : Partner's name
        *   p : Partner's picture
        *   s : Partner's status
        *  }
        * w : Chat windows, indexed by uid
        *  {
        *   d : Partner's data
        *    {
        *     u : Partner's uid
        *     n : Partner's name
        *     p : Partner's picture
        *     s : Partner's status
        *    }
        *   m : Messages, indexed by cmid
        *    {
        *     i : Message cmid
        *     r : 1 if it's a received message, 0 otherwise
        *     t : Message time (UNIX timestamp)
        *     b : Message body
        *    }
        *   e : Number of new messages
        *  }
        * }
        */

        /** @var $em \Doctrine\ORM\EntityManager */
        $em = $this->container->get('doctrine.orm.default_entity_manager');

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
            /*
            'w' => array(
                21 => array(
                    'd' => array(
                        'u' => 21,
                        'n' => "Foxy",
                        'p' => 'http://loopj.com/images/facebook_32.png',
                        's' => 1,
                    ),
                    'm' => array(
                        1 => array(
                            'i' => 1,
                            'r' => 1,
                            't' => time(),
                            'b' => 'lorem ipsum nesto ' . rand(1, 1999),
                        ),
                    ),
                    'e' => 1,
                ),
            ),
            */
            'w' => array(),
        );

        foreach($chatData['v'] as $openConversationId) {
            $chatData['w'][$openConversationId] = array(
                'd' => array(),
                'm' => array(),
                'e' => 0
            );
        }

        /** @var $messageRepository \App\NodejsBundle\Entity\ChatMessageRepository */
        $messageRepository = $em->getRepository('BriareosChatBundle:ChatMessage');
        $messages = $messageRepository->getSubjectMessages($subject);
        foreach($messages as $message) {
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
    }
}
