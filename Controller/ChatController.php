<?php

namespace Briareos\ChatBundle\Controller;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Briareos\NodejsBundle\Nodejs\Message;
use Briareos\NodejsBundle\Nodejs\DispatcherInterface;
use Briareos\NodejsBundle\Entity\NodejsPresence;
use Briareos\ChatBundle\Entity\ChatSubjectInterface;
use Briareos\ChatBundle\Entity\ChatMessage;
use Briareos\ChatBundle\Entity\ChatConversation;
use Briareos\ChatBundle\Entity\ChatState;

class ChatController extends ContainerAware
{
    public function getPicture(ChatSubjectInterface $subject)
    {
        /** @var $pictureProvider \Briareos\ChatBundle\Subject\PictureProviderInterface */
        $pictureProvider = $this->container->get('chat_subject.picture_provider');
        return $pictureProvider->getPicture($subject);
    }

    public function getSubject()
    {
        /** @var $em \Doctrine\ORM\EntityManager */
        $em = $this->container->get('doctrine.orm.default_entity_manager');
        $presenceRepository = $em->getRepository('BriareosNodejsBundle:NodejsPresence');
        $presence = $presenceRepository->findOneBy(array(
            'authToken' => $this->getAuthToken(),
        ));
        if ($presence instanceof NodejsPresence) {
            /** @var $presence NodejsPresence */
            return $presence->getSubject();
        }
        return null;
    }

    private function getAuthToken()
    {
        $request = $this->container->get('request');
        $authToken = $request->request->get('token');
        if (!is_scalar($authToken) || empty($authToken)) {
            throw new HttpException(400, 'AuthToken must be specified.');
        }
        return $authToken;
    }

    private function getNodejsCallback(ChatSubjectInterface $subject)
    {
        return 'chat_' . $subject->getId();
    }

    /**
     * @Route("/chat/cache", name="briareos_chat_cache")
     *
     * @return Response
     *
     * @throws AccessDeniedException
     */
    public function cacheAction()
    {
        /** @var $subject ChatSubjectInterface */
        $subject = $this->getSubject();

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

        /** @var $subjectRepository \Briareos\ChatBundle\Entity\ChatSubjectRepositoryInterface */
        $subjectRepository = $em->getRepository(get_class($subject));
        /** @var $stateRepository \Briareos\ChatBundle\Entity\ChatStateRepository */
        $stateRepository = $em->getRepository('BriareosChatBundle:ChatState');
        /** @var $chatState \Briareos\ChatBundle\Entity\ChatState */
        $chatState = $stateRepository->getChatState($subject);

        $chatData = array(
            'u' => $subject->getId(),
            'n' => $subject->getChatName(),
            'p' => $this->getPicture($subject),
            'a' => $chatState->getActiveConversationId(),
            'v' => $chatState->getOpenConversations(),
            'o' => $this->generatePresentSubjects($subjectRepository->getPresentSubjects($subject)),
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
                    'e' => 0,
                );
            }
            $messageText = $message->text;
            $chatData['w'][$message->partner_id]['m'][$message->id] = array(
                'i' => (int)$message->id,
                'r' => (bool)$message->received,
                't' => strtotime($message->createdAt),
                'b' => $messageText
            );
            if ($message->new) {
                $chatData['w'][$message->partner_id]['e']++;
            }
        }

        foreach ($chatData['w'] as $partnerId => &$window) {
            /** @var $partner ChatSubjectInterface */
            $partner = $subjectRepository->find($partnerId);
            $cacheKey = array_search($partnerId, $chatData['v']);
            if ($cacheKey === false) {
                $cache['v'][] = $partner->getId();
            }
            $window['d'] = array(
                'u' => $partner->getId(),
                'n' => $partner->getChatName(),
                'p' => $this->getPicture($partner),
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

    /**
     * @Route("/chat/activate", name="briareos_chat_activate")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function activateAction()
    {
        /** @var $subject ChatSubjectInterface */
        $subject = $this->getSubject();

        $partnerId = $this->container->get('request')->request->get('uid');

        $tid = $this->container->get('request')->request->get('tid', '');

        if (empty($tid) || !is_string($tid)) {
            throw new HttpException(400, 'Parameter "tid" is required and must be a string.');
        }

        if (!$subject instanceof ChatSubjectInterface) {
            throw new AccessDeniedException('User must be logged in to use chat.');
        }

        /** @var $em \Doctrine\ORM\EntityManager */
        $em = $this->container->get('doctrine.orm.default_entity_manager');
        /** @var $stateRepository \Briareos\ChatBundle\Entity\ChatStateRepository */
        $stateRepository = $em->getRepository('BriareosChatBundle:ChatState');
        $chatState = $stateRepository->getChatState($subject);

        if ($partnerId) {
            $subjectRepository = $em->getRepository(get_class($subject));
            /** @var $partner ChatSubjectInterface */
            $partner = $subjectRepository->find($partnerId);
            if (!$partner instanceof ChatSubjectInterface || $subject->getId() === $partner->getId()) {
                throw new HttpException(400, 'Invalid partner ID specified.');
            }
        } else {
            $partner = null;
        }

        $chatState->setActiveConversation($partner);
        if ($partner) {
            $chatState->addOpenConversation($partner->getId());
        }
        $em->persist($chatState);
        $em->flush($chatState);

        if ($partner) {
            /** @var $conversationRepository \Briareos\ChatBundle\Entity\ChatConversationRepository */
            $conversationRepository = $em->getRepository('BriareosChatBundle:ChatConversation');
            $chatConversation = $conversationRepository->getConversation($subject, $partner);
            /** @var $messageRepository \Briareos\ChatBundle\Entity\ChatMessageRepository */
            $messageRepository = $em->getRepository('BriareosChatBundle:ChatMessage');

            /** @var $lastMessageFromThisPartner ChatMessage */
            $lastMessageFromThisPartner = $messageRepository->getLastMessageFromTo($partner, $subject);
            if ($lastMessageFromThisPartner !== null) {
                $chatConversation->setLastMessageRead($lastMessageFromThisPartner->getId());
                $em->persist($chatConversation);
                $em->flush($chatConversation);
            }
        }

        /** @var $dispatcher DispatcherInterface */
        $dispatcher = $this->container->get('nodejs.dispatcher');
        $activateMessage = new Message($this->getNodejsCallback($subject));
        $activateMessage->setChannel('user_' . $subject->getId());
        if ($partner) {
            $messageData = array(
                'command' => 'activate',
                'tid' => $tid,
                'uid' => $partner->getId(),
                'd' => array(
                    'u' => $partner->getId(),
                    'n' => $partner->getChatName(),
                    'p' => $this->getPicture($subject),
                ),
            );
        } else {
            $messageData = array(
                'command' => 'activate',
                'tid' => $tid,
                'uid' => 0,
            );
        }
        $activateMessage->setData($messageData);
        $dispatcher->dispatch($activateMessage);

        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * @Route("/chat/close", name="briareos_chat_close")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function closeAction()
    {
        /** @var $subject ChatSubjectInterface */
        $subject = $this->getSubject();

        $tid = $this->container->get('request')->request->get('tid', '');

        if (empty($tid) || !is_string($tid)) {
            throw new HttpException(400, 'Parameter "tid" is required and must be a string.');
        }

        if (!$subject instanceof ChatSubjectInterface) {
            throw new AccessDeniedException();
        }

        /** @var $em \Doctrine\ORM\EntityManager */
        $em = $this->container->get('doctrine.orm.default_entity_manager');
        $connection = $em->getConnection();
        /** @var $stateRepository \Briareos\ChatBundle\Entity\ChatStateRepository */
        $stateRepository = $em->getRepository('BriareosChatBundle:ChatState');
        $chatState = $stateRepository->getChatState($subject);

        $subjectRepository = $em->getRepository(get_class($subject));

        $partnerId = $this->container->get('request')->request->get('uid');
        /** @var $partner ChatSubjectInterface */
        $partner = $subjectRepository->find($partnerId);

        if (!$partner || !$partner instanceof ChatSubjectInterface || $subject->getId() === $partner->getId()) {
            throw new HttpException(400, 'Invalid partner ID specified.');
        }

        /** @var $result \Doctrine\DBAL\Driver\Statement */
        $result = $connection->executeQuery('
            SELECT message.id FROM chat__message message
            WHERE (message.sender_id = :subject_id AND message.receiver_id = :partner_id) OR (message.receiver_id = :subject_id AND message.sender_id = :partner_id)
            ORDER BY message.id DESC LIMIT 1
            ', array(
            ':subject_id' => $subject->getId(),
            ':partner_id' => $partner->getId(),
        ));
        $lastMessageId = $result->fetchColumn();
        if ($lastMessageId) {
            $connection->executeUpdate('
                UPDATE chat__conversation conversation
                SET conversation.lastMessageCleared = :lastMessageCleared
                WHERE conversation.subject_id = :subject_id AND conversation.partner_id = :partner_id
                ', array(
                ':lastMessageCleared' => $lastMessageId,
                ':subject_id' => $subject->getId(),
                ':partner_id' => $partner->getId(),
            ));
        }

        /** @var $dispatcher DispatcherInterface */
        $dispatcher = $this->container->get('nodejs.dispatcher');
        $closeMessage = new Message($this->getNodejsCallback($subject));
        $closeMessage->setData(array(
            'command' => 'close',
            'tid' => $tid,
            'uid' => $partner->getId(),
        ));
        $closeMessage->setChannel('user_' . $subject->getId());
        $dispatcher->dispatch($closeMessage);

        /** @var $em \Doctrine\ORM\EntityManager */
        if ($chatState->getActiveConversationId() && $chatState->getActiveConversationId() === $partner->getId()) {
            $chatState->setActiveConversation(null);
        }
        $chatState->removeOpenConversation($partner->getId());
        $em->flush($chatState);

        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * @Route("/chat/ping", name="briareos_chat_ping")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function pingAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * @Route("/chat/send", name="briareos_chat_send")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function sendAction()
    {
        /** @var $subject ChatSubjectInterface */
        $subject = $this->getSubject();

        $tid = $this->container->get('request')->request->get('tid', '');

        if (empty($tid) || !is_string($tid)) {
            throw new HttpException(400, 'Parameter "tid" is required and must be a string.');
        }

        if (!$subject instanceof ChatSubjectInterface) {
            throw new AccessDeniedException();
        }

        /** @var $em \Doctrine\ORM\EntityManager */
        $em = $this->container->get('doctrine.orm.default_entity_manager');
        /** @var $stateRepository \Briareos\ChatBundle\Entity\ChatStateRepository */
        $stateRepository = $em->getRepository('BriareosChatBundle:ChatState');
        $subjectChatState = $stateRepository->getChatState($subject);

        $subjectRepository = $em->getRepository(get_class($subject));

        $partnerId = $this->container->get('request')->request->get('uid');
        /** @var $partner ChatSubjectInterface */
        $partner = $subjectRepository->find($partnerId);
        $partnerChatState = $stateRepository->getChatState($partner);

        if (!$partner || !$partner instanceof ChatSubjectInterface || $subject->getId() === $partner->getId()) {
            throw new HttpException(400, 'Invalid partner ID specified.');
        }

        $messageText = $this->container->get('request')->request->get('message');

        $message = new ChatMessage();
        $message->setSender($subject);
        $message->setReceiver($partner);
        $message->setText($messageText);
        $em->persist($message);

        if ($subjectChatState->getActiveConversationId() && $partnerChatState->getActiveConversationId() === $subjectChatState->getActiveConversationId()) {
            /** @var $conversationRepository \Briareos\ChatBundle\Entity\ChatConversationRepository */
            $conversationRepository = $em->getRepository('BriareosChatBundle:ChatConversation');
            $chatConversation = $conversationRepository->getConversation($subject, $partner);
            $chatConversation->setLastMessageRead($message->getId());
            $em->persist($chatConversation);
        }
        $em->flush();

        /** @var $dispatcher DispatcherInterface */
        $dispatcher = $this->container->get('nodejs.dispatcher');

        $messageData = array(
            'command' => 'message',
            'tid' => $tid,
            'sender' => array(
                'u' => $subject->getId(),
                'n' => $subject->getChatName(),
                'p' => $this->getPicture($subject),
            ),
            'receiver' => array(
                'u' => $partner->getId(),
                'n' => $partner->getChatName(),
                'p' => $this->getPicture($partner),
            ),
            'message' => array(
                'i' => (int)$message->getId(),
                't' => $message->getCreatedAt()->getTimestamp(),
                'b' => $message->getText(),
            ),
        );

        $nodejsMessageToSender = new Message($this->getNodejsCallback($subject));
        $nodejsMessageToSender->setData($messageData);
        $nodejsMessageToSender->setChannel('user_' . $subject->getId());
        $dispatcher->dispatch($nodejsMessageToSender);

        $nodejsMessageToReceiver = new Message($this->getNodejsCallback($partner));
        $nodejsMessageToReceiver->setData($messageData);
        $nodejsMessageToReceiver->setChannel('user_' . $partner->getId());
        $dispatcher->dispatch($nodejsMessageToReceiver);

        return new Response();
    }

    public function generatePresentSubjects(array $subjects)
    {
        $presence = array();
        /** @var $subject ChatSubjectInterface */
        foreach ($subjects as $subject) {
            $presence[$subject->getId()] = array(
                'u' => $subject->getId(),
                'n' => $subject->getChatName(),
                'p' => $this->getPicture($subject),
                's' => 1,
            );
        }
        return $presence;
    }
}
