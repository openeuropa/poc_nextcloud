<?php

namespace OCA\UserCAS\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class Section implements IIconSection
{
    /** @var IL10N */
//private $l;
    /** @var IURLGenerator */
    private $url;

    /**
     * @param IURLGenerator $url
     */
    public function __construct(
        IURLGenerator $url)
    {
        $this->url = $url;
    }

    /**
     * {@inheritdoc}
     */
    public function getID()
    {
        return 'eulogin';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'EULOGIN authentication';
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority()
    {
        return 75;
    }

    /**
     * {@inheritdoc}
     */
    public function getIcon()
    {
        return $this->url->imagePath('user_cas', 'app-dark.svg');
    }
}
