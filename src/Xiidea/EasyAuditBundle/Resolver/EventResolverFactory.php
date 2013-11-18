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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Xiidea\EasyAuditBundle\Traits\ServiceContainerGetterMethods;

class EventResolverFactory
{
    use ServiceContainerGetterMethods;

    public function __construct(ContainerInterface $container)
    {

        $this->container = $container;
    }

    public function getEventLog($event)
    {
        $eventLog = $this->getEventLogInfo($event);

        return $this->getEventLogObject($eventLog);
    }

    protected function getEventLogObject($eventInfo)
    {
        $auditLogClass = $this->getParameter('entity_class');

        if (is_array($eventInfo)) {

            $eventObject = new $auditLogClass();
            $fromArray = $eventObject->fromArray($eventInfo);

            return $fromArray;

        } elseif ($eventInfo instanceof $auditLogClass) {
            return $eventInfo;
        }

        if ($this->getKernel()->isDebug()) {
            throw new \Exception('Unrecognized Event info');
        }

        return NULL;
    }

    protected function eventWithResolver($event)
    {
        return ($event instanceof EventResolverInterface) || method_exists($event, 'getEventLogInfo');
    }


    /**
     * @param $eventName
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
                    throw new \Exception('Resolver Service must implement' . __NAMESPACE__ . "EventResolverInterface");
                }
            }

            return $resolver;
        }

        return $this->getCommonResolver();
    }

    protected function isEntityEvent($eventName)
    {
        return in_array($eventName, $this->getDoctrineEventsList());
    }

    protected function getEventLogInfo($event)
    {
        if ($this->eventWithResolver($event)) {
            return $event->getEventLogInfo();
        }

        $eventResolver = $this->getResolver($event->getName());

        return $eventResolver->getEventLogInfo($event);
    }

}