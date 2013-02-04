<?php

namespace Briareos\ChatBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Briareos\NodejsBundle\Nodejs\Message;
use Briareos\NodejsBundle\Entity\NodejsPresence;
use Briareos\ChatBundle\Entity\ChatSubjectInterface;
use Briareos\ChatBundle\Entity\ChatMessage;

class ChatController
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $em;

    /**
     * @var \Briareos\NodejsBundle\Nodejs\DispatcherInterface
     */
    private $dispatcher;

    /**
     * @var \Briareos\ChatBundle\Subject\PictureProviderInterface
     */
    private $pictureProvider;

    /**
     * @var \Briareos\ChatBundle\Subject\PresenceProviderInterface
     */
    private $presenceProvider;


    function __construct($em, $dispatcher, $pictureProvider, $presenceProvider)
    {
        $this->dispatcher = $dispatcher;
        $this->em = $em;
        $this->pictureProvider = $pictureProvider;
        $this->presenceProvider = $presenceProvider;
    }

    public function getPicture(ChatSubjectInterface $subject)
    {
        return $this->pictureProvider->getSubjectPicture($subject);
    }

    public function getSubject(Request $request)
    {
        $presenceRepository = $this->em->getRepository('BriareosNodejsBundle:NodejsPresence');
        $presence = $presenceRepository->findOneBy(
            array(
                'authToken' => $this->getAuthToken($request),
            )
        );
        if ($presence instanceof NodejsPresence) {
            /** @var $presence NodejsPresence */
            return $presence->getSubject();
        }

        return null;
    }

    private function getAuthToken(Request $request)
    {
        $authToken = $request->request->get('token');
        if (!is_scalar($authToken) || empty($authToken)) {
            throw new HttpException(400, 'AuthToken must be specified.');
        }

        return $authToken;
    }

    private function getNodejsCallback(ChatSubjectInterface $subject)
    {
        return sprintf('chat_%s', $subject->getId());
    }

    /**
     * Controller action.
     *
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return Response
     */
    public function cacheAction(Request $request)
    {
        /** @var $subject ChatSubjectInterface */
        $subject = $this->getSubject($request);

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

        $subjectRepository = $this->em->getRepository(get_class($subject));
        /** @var $stateRepository \Briareos\ChatBundle\Entity\ChatStateRepository */
        $stateRepository = $this->em->getRepository('BriareosChatBundle:ChatState');
        $chatState = $stateRepository->getChatState($subject);
        $presentSubjects = $this->presenceProvider->getPresentSubjects($subject);

        $chatData = array(
            'u' => $subject->getId(),
            'n' => $subject->getChatName(),
            'p' => $this->getPicture($subject),
            'a' => $chatState->getActiveConversationId() ? $chatState->getActiveConversationId() : 0,
            'v' => $chatState->getOpenConversations(),
            'o' => $this->generatePresentSubjects($presentSubjects),
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
        $messageRepository = $this->em->getRepository('BriareosChatBundle:ChatMessage');
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
                'i' => (int) $message->id,
                'r' => (bool) $message->received,
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
            $this->em->flush($chatState);
        }

        $response = new JsonResponse($chatData);

        return $response;
    }

    /**
     * Controller action.
     *
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function activateAction(Request $request)
    {
        /** @var $subject ChatSubjectInterface */
        $subject = $this->getSubject($request);

        $partnerId = $request->request->getInt('uid');

        $sid = $request->request->get('sid', '');

        if (empty($sid) || !is_string($sid)) {
            throw new HttpException(400, 'Parameter "sid" (socket ID) is required and must be a string.');
        }

        if (!$subject instanceof ChatSubjectInterface) {
            throw new AccessDeniedException('User must be logged in to use chat.');
        }

        /** @var $stateRepository \Briareos\ChatBundle\Entity\ChatStateRepository */
        $stateRepository = $this->em->getRepository('BriareosChatBundle:ChatState');
        $chatState = $stateRepository->getChatState($subject);

        if ($partnerId) {
            $subjectRepository = $this->em->getRepository(get_class($subject));
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
        $this->em->persist($chatState);
        $this->em->flush($chatState);

        if ($partner) {
            /** @var $conversationRepository \Briareos\ChatBundle\Entity\ChatConversationRepository */
            $conversationRepository = $this->em->getRepository('BriareosChatBundle:ChatConversation');
            $chatConversation = $conversationRepository->getConversation($subject, $partner);
            /** @var $messageRepository \Briareos\ChatBundle\Entity\ChatMessageRepository */
            $messageRepository = $this->em->getRepository('BriareosChatBundle:ChatMessage');

            /** @var $lastMessageFromThisPartner ChatMessage */
            $lastMessageFromThisPartner = $messageRepository->getLastMessageFromTo($partner, $subject);
            if ($lastMessageFromThisPartner !== null) {
                $chatConversation->setLastMessageRead($lastMessageFromThisPartner->getId());
                $this->em->persist($chatConversation);
                $this->em->flush($chatConversation);
            }
        }

        $activateMessage = new Message($this->getNodejsCallback($subject));
        $activateMessage->setChannel($this->getSubjectChannel($subject));
        if ($partner) {
            $messageData = array(
                'command' => 'activate',
                'sid' => $sid,
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
                'sid' => $sid,
                'uid' => 0,
            );
        }
        $activateMessage->setData($messageData);
        $this->dispatcher->dispatch($activateMessage);

        $response = new JsonResponse();

        return $response;
    }

    /**
     * Controller action.
     *
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function closeAction(Request $request)
    {
        /** @var $subject ChatSubjectInterface */
        $subject = $this->getSubject($request);

        $sid = $request->request->get('sid', '');

        if (empty($sid) || !is_string($sid)) {
            throw new HttpException(400, 'Parameter "sid" (socket ID) is required and must be a string.');
        }

        if (!$subject instanceof ChatSubjectInterface) {
            throw new AccessDeniedException();
        }

        $connection = $this->em->getConnection();
        /** @var $stateRepository \Briareos\ChatBundle\Entity\ChatStateRepository */
        $stateRepository = $this->em->getRepository('BriareosChatBundle:ChatState');
        $chatState = $stateRepository->getChatState($subject);

        $subjectRepository = $this->em->getRepository(get_class($subject));

        $partnerId = $request->request->getInt('uid');
        /** @var $partner ChatSubjectInterface */
        $partner = $subjectRepository->find($partnerId);

        if (!$partner || !$partner instanceof ChatSubjectInterface || $subject->getId() === $partner->getId()) {
            throw new HttpException(400, 'Invalid partner ID specified.');
        }

        /** @var $result \Doctrine\DBAL\Driver\Statement */
        $result = $connection->executeQuery(
            '
            SELECT message.id FROM chat__message message
            WHERE (message.sender_id = :subject_id AND message.receiver_id = :partner_id) OR (message.receiver_id = :subject_id AND message.sender_id = :partner_id)
            ORDER BY message.id DESC LIMIT 1
            ',
            array(
                ':subject_id' => $subject->getId(),
                ':partner_id' => $partner->getId(),
            )
        );
        $lastMessageId = $result->fetchColumn();
        if ($lastMessageId) {
            $connection->executeUpdate(
                '
                UPDATE chat__conversation conversation
                SET conversation.lastMessageCleared = :lastMessageCleared
                WHERE conversation.subject_id = :subject_id AND conversation.partner_id = :partner_id
                ',
                array(
                    ':lastMessageCleared' => $lastMessageId,
                    ':subject_id' => $subject->getId(),
                    ':partner_id' => $partner->getId(),
                )
            );
        }

        $closeMessage = new Message($this->getNodejsCallback($subject));
        $closeMessage->setData(
            array(
                'command' => 'close',
                'sid' => $sid,
                'uid' => $partner->getId(),
            )
        );
        $closeMessage->setChannel($this->getSubjectChannel($subject));
        $this->dispatcher->dispatch($closeMessage);

        /** @var $em \Doctrine\ORM\EntityManager */
        if ($chatState->getActiveConversationId() && $chatState->getActiveConversationId() === $partner->getId()) {
            $chatState->setActiveConversation(null);
        }
        $chatState->removeOpenConversation($partner->getId());
        $this->em->flush($chatState);

        $response = new JsonResponse();

        return $response;
    }

    public function getSubjectChannel(ChatSubjectInterface $subject)
    {
        return sprintf('user_%s', $subject->getId());
    }

    /**
     * Controller action.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function pingAction()
    {
        $response = new JsonResponse();

        return $response;
    }

    /**
     * Controller action.
     *
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function sendAction(Request $request)
    {
        /** @var $subject ChatSubjectInterface */
        $subject = $this->getSubject($request);

        $sid = $request->request->get('sid', '');

        if (empty($sid) || !is_string($sid)) {
            throw new HttpException(400, 'Parameter "sid" (socket ID) is required and must be a string.');
        }

        if (!$subject instanceof ChatSubjectInterface) {
            throw new AccessDeniedException();
        }

        /** @var $stateRepository \Briareos\ChatBundle\Entity\ChatStateRepository */
        $stateRepository = $this->em->getRepository('BriareosChatBundle:ChatState');
        $subjectChatState = $stateRepository->getChatState($subject);

        $subjectRepository = $this->em->getRepository(get_class($subject));

        $partnerId = $request->request->getInt('uid');
        /** @var $partner ChatSubjectInterface */
        $partner = $subjectRepository->find($partnerId);
        $partnerChatState = $stateRepository->getChatState($partner);

        if (!$partner || !$partner instanceof ChatSubjectInterface || $subject->getId() === $partner->getId()) {
            throw new HttpException(400, 'Invalid partner ID specified.');
        }

        $messageText = $request->request->get('message');

        $message = new ChatMessage();
        $message->setSender($subject);
        $message->setReceiver($partner);
        $message->setText($messageText);
        $this->em->persist($message);

        if ($partnerChatState->getActiveConversationId() && $partnerChatState->getActiveConversationId() === $subject->getId()) {
            /** @var $conversationRepository \Briareos\ChatBundle\Entity\ChatConversationRepository */
            $conversationRepository = $this->em->getRepository('BriareosChatBundle:ChatConversation');
            $chatConversation = $conversationRepository->getConversation($partner, $subject);
            // We need the message ID.
            $this->em->flush($message);
            $chatConversation->setLastMessageRead($message->getId());
            $this->em->persist($chatConversation);
        }
        $this->em->flush();

        $messageData = array(
            'command' => 'message',
            'sid' => $sid,
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
                'i' => (int) $message->getId(),
                't' => $message->getCreatedAt()->getTimestamp(),
                'b' => $message->getText(),
            ),
        );

        $nodejsMessageToSender = new Message($this->getNodejsCallback($subject));
        $nodejsMessageToSender->setData($messageData);
        $nodejsMessageToSender->setChannel($this->getSubjectChannel($subject));
        $this->dispatcher->dispatch($nodejsMessageToSender);

        $nodejsMessageToReceiver = new Message($this->getNodejsCallback($partner));
        $nodejsMessageToReceiver->setData($messageData);
        $nodejsMessageToReceiver->setChannel($this->getSubjectChannel($partner));
        $this->dispatcher->dispatch($nodejsMessageToReceiver);

        return new JsonResponse();
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
