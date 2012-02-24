<?php

namespace Lexik\Bundle\TranslationBundle\Model;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @UniqueEntity(fields={"key", "domain"})
 *
 * @author Cédric Girard <c.girard@lexik.fr>
 */
abstract class TransUnit
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     *
     * @Assert\NotBlank()
     */
    protected $key;

    /**
     * @var string
     *
     * @Assert\NotBlank()
     */
    protected $domain;

    /**
     * @var Doctrine\Common\Collections\Collection
     */
    protected $translations;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime
     */
    protected $updatedAt;

    /**
     * Construct.
     */
    public function __construct()
    {
        $this->domain = 'messages';
        $this->translations = new ArrayCollection();
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set key name
     *
     * @param string $key
     */
    public function setKey($key)
    {
        $this->key = $key;
    }

    /**
     * Get key name
     *
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Set domain
     *
     * @param string $domain
     */
    public function setDomain($domain)
    {
      $this->domain = $domain;
    }

    /**
     * Get domain
     *
     * @return string
     */
    public function getDomain()
    {
      return $this->domain;
    }

    /**
     * Add translations
     *
     * @param Lexik\Bundle\TranslationBundle\Model\Translation $translations
     */
    public function addTranslation(\Lexik\Bundle\TranslationBundle\Model\Translation $translation)
    {
        $translation->setTransUnit($this);

        $this->translations[] = $translation;
    }

    /**
     * Get translations
     *
     * @return Doctrine\Common\Collections\Collection
     */
    public function getTranslations()
    {
        return $this->translations;
    }

    /**
     * Return true if this object has a translation for the given locale.
     *
     * @param string $locale
     * @return boolean
     */
    public function hasTranslation($locale)
    {
        $i = 0;
        $end = count($this->getTranslations());
        $found = false;

        while ($i<$end && !$found) {
            $found = ($this->translations[$i]->getLocale() == $locale);
            $i++;
        }

        return $found;
    }

    /**
     * Set translations collection
     *
     * @param Collection $collection
     */
    public function setTranslations(Collection $collection)
    {
        $this->translations = $collection;
    }

    /**
     * Return transaltions with  not blank content.
     *
     * @return Doctrine\Common\Collections\Collection
     */
    public function filterNotBlankTranslations()
    {
        return $this->getTranslations()->filter(function ($translation) {
            $content = $translation->getContent();
            return !empty($content);
        });
    }

    /**
     * Get createdAt
     *
     * @return datetime $createdAt
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Get updatedAt
     *
     * @return datetime $updatedAt
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * Things to do on prePersist
     */
    public function prePersist()
    {
        $this->createdAt = new \DateTime("now");
        $this->updatedAt = new \DateTime("now");
    }

    /**
     * Things to do on preUpdate
     */
    public function preUpdate()
    {
        $this->updatedAt = new \DateTime("now");
    }
}