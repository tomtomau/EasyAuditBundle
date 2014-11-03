<?php

/*
 * This file is part of the XiideaEasyAuditBundle package.
 *
 * (c) Xiidea <http://www.xiidea.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Xiidea\EasyAuditBundle\Resolver;

use Xiidea\EasyAuditBundle\Common\UserAwareComponent;
use Xiidea\EasyAuditBundle\Entity\BaseAuditLog;
use Xiidea\EasyAuditBundle\Exception\InvalidServiceException;
use Xiidea\EasyAuditBundle\Exception\UnrecognizedEventInfoException;
use Xiidea\EasyAuditBundle\Traits\ServiceContainerGetterMethods;
use Symfony\Component\EventDispatcher\Event;

class EventResolverFactory extends UserAwareComponent
{
    use ServiceContainerGetterMethods;

    public function getEventLog(Event $event)
    {
        $eventLog = $this->getEventLogObject($this->getEventLogInfo($event));

        if ($eventLog === null) {
            return null;
        }

        $eventLog->setTypeId($event->getName());
        $eventLog->setIp($this->getClientIp());
        $eventLog->setEventTime(new \DateTime());
        $this->setUser($eventLog);

        return $eventLog;
    }

    /**
     * @param $eventInfo
     *
     * @return null|BaseAuditLog
     * @throws UnrecognizedEventInfoException
     */
    protected function getEventLogObject($eventInfo)
    {
        $auditLogClass = $this->getParameter('entity_class');

        if (is_array($eventInfo)) {

            $eventObject = new $auditLogClass();
            $fromArray = $eventObject->fromArray($eventInfo);

            return $fromArray;

        } elseif ($eventInfo instanceof $auditLogClass) {
            return $eventInfo;
        } elseif (empty($eventInfo)) {
            return NULL;
        }

        if ($this->getKernel()->isDebug()) {
            throw new UnrecognizedEventInfoException();
        }

        return NULL;
    }

    /**
     * @param string $eventName
     *
     * @throws \Exception
     * @return EventResolverInterface
     */
    protected function getResolver($eventName)
    {
        $customResolvers = $this->getParameter('custom_resolvers');

        if ($this->isEntityEvent($eventName)) {
            return $this->getEntityEventResolver();
        } elseif (isset($customResolvers[$eventName])) {

            $resolver = $this->getService($customResolvers[$eventName]);

            if (!$resolver instanceof EventResolverInterface) {
                if ($this->getKernel()->isDebug()) {
                    throw new InvalidServiceException('Resolver Service must implement' . __NAMESPACE__ . "EventResolverInterface");
                }

                return null;
            }

            return $resolver;
        }

        return $this->getCommonResolver();
    }

    protected function isEntityEvent($eventName)
    {
        return in_array($eventName, $this->getDoctrineEventsList());
    }

    protected function getEventLogInfo(Event $event)
    {
        if ($event instanceof EmbeddedEventResolverInterface) {
            return $event->getEventLogInfo();
        }

        if (null === $eventResolver = $this->getResolver($event->getName())) {
            return null;
        }

        return $eventResolver->getEventLogInfo($event);
    }

    protected function setUser(BaseAuditLog $entity)
    {
        $userProperty = $this->container->getParameter('xiidea.easy_audit.user_property');

        $user = $this->getUser();

        if (empty($userProperty)) {
            $entity->setUser($user);
        } elseif ($user && is_callable(array($user, "get{$userProperty}"))) {
            $propertyGetter = "get{$userProperty}";
            $entity->setUser($user->$propertyGetter());
        } elseif ($user === NULL) {
            $entity->setUser($this->getAnonymousUserName());
        } elseif ($this->isDebug()) {
            throw new \Exception("get{$userProperty}() not found in user object");
        }
    }

    protected function isDebug()
    {
        return $this->container->get('kernel')->isDebug();
    }

    /**
     * @return string
     */
    protected function getClientIp()
    {
        try {
            return $this->container->get('request')->getClientIp();
        } catch (\Exception $e) {
            return "";
        }
    }

    /**
     * @return \Symfony\Component\DependencyInjection\ContainerInterface
     */
    protected function getContainer()
    {
        return $this->container;
    }
}
