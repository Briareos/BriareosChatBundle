<?php

namespace Briareos\ChatBundle\Listener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Briareos\ChatBundle\Entity\ChatSubjectInterface;

class ChatSubjectRemoveListener
{

    /**
     * Since open chat windows are comma-separated integers (user IDs), remove them manually when users are removed from
     * the database.
     *
     * @param \Doctrine\ORM\Event\LifecycleEventArgs $args
     */
    public function preRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if ($entity instanceof ChatSubjectInterface) {
            /** @var $entity ChatSubjectInterface */
            /** @var $chatStateRepository \Briareos\ChatBundle\Entity\ChatStateRepository */
            $chatStateRepository = $args->getEntityManager()->getRepository('BriareosChatBundle:ChatState');
            $openChatStates = $chatStateRepository->getOpenStates($entity);
            if ($openChatStates) {
                $em = $args->getEntityManager();
                foreach ($openChatStates as $openChatState) {
                    /** @var $openChatState \Briareos\ChatBundle\Entity\ChatState */
                    $openChatState->removeOpenConversation($entity->getId());
                    $em->persist($openChatState);
                }
                $em->flush();
            }
        }
    }
}
