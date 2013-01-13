<?php

/*
 * This file is part of the FOSUserBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Night\UserBundle\Doctrine;

use Doctrine\Common\Persistence\ObjectManager;
use FOS\UserBundle\Model\UserInterface;
use FOS\UserBundle\Doctrine\UserManager as BaseUserManager;
use FOS\UserBundle\Util\CanonicalizerInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Night\CommonBundle\Utils\EntityUtil;

class UserManager extends BaseUserManager {

	protected $objectManager;
	protected $class;
	protected $repository;
	protected $entityUtil;
	protected $uploadDir = "web/uploads/users";
	protected $files;

	/**
	 * Constructor.
	 *
	 * @param EncoderFactoryInterface $encoderFactory
	 * @param CanonicalizerInterface  $usernameCanonicalizer
	 * @param CanonicalizerInterface  $emailCanonicalizer
	 * @param ObjectManager           $om
	 * @param string                  $class
	 */
	public function __construct(EncoderFactoryInterface $encoderFactory, CanonicalizerInterface $usernameCanonicalizer, CanonicalizerInterface $emailCanonicalizer, ObjectManager $om, $class) {
		parent::__construct($encoderFactory, $usernameCanonicalizer, $emailCanonicalizer, $om, $class);
		$this->entityUtil = new EntityUtil();
		$this->files = (!empty($this->files)) ? $this->files : $this->entityUtil->getFilesProperties($this->class);
	}

	/**
	 * Updates a user.
	 *
	 * @param UserInterface $user
	 * @param Boolean       $andFlush Whether to flush the changes (default true)
	 */
	public function updateUser(UserInterface $user, $andFlush = true) {
		$this->updateCanonicalFields($user);
		$this->updatePassword($user);
		$this->handleUploadedFiles($user);

		$this->objectManager->persist($user);

		if ($andFlush) {
			$this->objectManager->flush();
		}
	}
//
//	public function updatePassword(UserInterface $user) {
//		if (0 !== strlen($password = $user->getPlainPassword())) {
//			$encoder = $this->getEncoder($user);
//			$user->setPassword($encoder->encodePassword($password, $user->getSalt()));
//			$user->eraseCredentials();
//		}
//	}

	public function handleUploadedFiles($entity) {
		foreach ($this->files as $fileProperty) {
			$fileGetter = $this->entityUtil->propToGetter($fileProperty);
			$fileNameGetter = $this->entityUtil->propToGetter($this->entityUtil->filePropToFilenameProp($fileProperty));
			$fileNameSetter = $this->entityUtil->propToSetter($this->entityUtil->filePropToFilenameProp($fileProperty));
			$previousFile = $this->getPreviousUploadedFile($entity, $fileNameGetter);

			// === Si un fichier a ete charge
			if ($entity->$fileGetter() && is_object($entity->$fileGetter())) {

				// $file est une instance de Symfony\Component\HttpFoundation\File\UploadedFile
				$file = $entity->$fileGetter();
				$filename = $this->entityUtil->generateRandomFileName($file);
				$file->move($this->getUploadRootDir(), $filename);

				unset($file);
				$this->getPreviousUploadedFile($entity, $fileNameGetter);
				$entity->$fileNameSetter($filename);
				$this->getPreviousUploadedFile($entity, $fileNameGetter);

				// Suppression de l'ancien fichier si existant
				if ($previousFile) {
					$this->removeUploadedFile($previousFile);
				}
			}
			// === Si aucun fichier n'a ete charge mais que l'on veut supprimer le fichier
			elseif ($this->getExecDeleteFile($entity, $fileGetter)) {
				$this->removeUploadedFile($entity->$fileNameGetter());
				$entity->$fileNameSetter(null);
			}
		}
	}

	public function getPreviousUploadedFile($entity, $fileGetter) {
		// === On verfie que l''on est dans le cas d'un upgrade d'entie
		if ($entity->getId()) {
			$previousEntity = $this->repository->find($entity->getId());
			return $previousEntity->$fileGetter();
		}
		return null;
	}

	public function removeUploadedFile($file) {
		$file = $this->getUploadRootDir() . '/' . $file;
		unlink($file);
	}

	public function containsFile($entity) {
		foreach ($this->files as $fileProperty) {
			$fileGetter = $this->entityUtil->propToGetter($fileProperty);
			$file = $entity->$fileGetter();
			return empty($file);
		}
	}

	public function getExecDeleteFile($entity, $fileGetter) {
		$method = substr($fileGetter, 0, -3) . "Delete";
		return $entity->$method();
	}

	public function getUploadDir() {
		return $this->uploadDir;
	}

	public function getUploadRootDir() {
		//var_dump(realpath( __DIR__ . '/../../../../../../' . $this->getUploadDir())); exit;
		return __DIR__ . '/../../../../../../' . $this->getUploadDir();
	}

}
