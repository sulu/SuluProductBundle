<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Tests\Resources;

use Doctrine\Common\Persistence\ObjectManager;
use Sulu\Bundle\ContactBundle\Contact\AccountFactoryInterface;
use Sulu\Bundle\ContactBundle\Contact\AccountManager;
use Sulu\Bundle\ContactBundle\Contact\ContactManagerInterface;
use Sulu\Bundle\ContactBundle\Entity\AccountInterface;
use Sulu\Bundle\ContactBundle\Entity\Address;
use Sulu\Bundle\ContactBundle\Entity\AddressType;
use Sulu\Bundle\ContactBundle\Entity\Country;
use Sulu\Bundle\ContactBundle\Entity\Email;
use Sulu\Bundle\ContactBundle\Entity\EmailType;
use Sulu\Component\Contact\Model\ContactInterface;
use Symfony\Component\DependencyInjection\Container;

class ContactTestData
{
    use TestDataTrait;

    const BASIC_TYPE = 0;
    const CUSTOMER_TYPE = 2;
    const SUPPLIER_TYPE = 3;

    /**
     * @var ContactInterface
     */
    public $contact;

    /**
     * @var AccountInterface
     */
    public $account;

    /**
     * @var AccountInterface
     */
    public $accountCustomer;

    /**
     * @var AccountInterface
     */
    public $accountCustomer2;

    /**
     * @var AccountInterface
     */
    public $accountSupplier;

    /**
     * @var AccountInterface
     */
    public $accountSupplier2;

    /**
     * @var Email
     */
    public $email;

    /**
     * @var EmailType
     */
    public $emailType;

    /**
     * @var Country
     */
    public $country;

    /**
     * @var AddressType
     */
    public $addressType;

    /**
     * @var Address
     */
    public $address;

    /**
     * @var ObjectManager
     */
    private $entityManager;

    /**
     * @var Container
     */
    private $container;

    /**
     * @var AccountFactoryInterface
     */
    private $accountFactory;

    /**
     * @var int
     */
    private $accountCount = 0;

    /**
     * @var int
     */
    private $contactCount = 0;

    /**
     * @param Container $container
     */
    public function __construct(
        Container $container
    ) {
        $this->container = $container;

        $this->entityManager = $container->get('doctrine.orm.entity_manager');
        $this->accountFactory = $this->container->get('sulu_contact.account_factory');

        $this->createFixtures();
    }

    /**
     * Create fixtures for product test data.
     */
    protected function createFixtures()
    {
        // address
        $country = new Country();
        $country->setName('Fantasyland');
        $country->setCode('fl');
        $this->country = $country;

        $addressType = new AddressType();
        $addressType->setName('Private');
        $this->addressType = $addressType;

        $this->address = $this->createAddress();

        // email
        $emailType = new EmailType();
        $emailType->setName('Private');
        $this->emailType = $emailType;

        $email = new Email();
        $email->setEmail('max.mustermann@muster.at');
        $email->setEmailType($emailType);
        $this->email = $email;

        // create contact
        $this->contact = $this->createContact();

        // create accounts
        $this->account = $this->createAccount();

        $this->accountCustomer = $this->createAccount(self::CUSTOMER_TYPE);
        $this->accountCustomer2 = $this->createAccount(self::CUSTOMER_TYPE);
        $this->accountSupplier = $this->createAccount(self::SUPPLIER_TYPE);
        $this->accountSupplier2 = $this->createAccount(self::SUPPLIER_TYPE);

        $this->accountCustomer->setMainEmail($email->getEmail());
        $this->accountCustomer2->setMainEmail($email->getEmail());
        $this->accountSupplier->setMainEmail($email->getEmail());
        $this->accountSupplier2->setMainEmail($email->getEmail());

        $this->entityManager->persist($addressType);
        $this->entityManager->persist($country);
        $this->entityManager->persist($emailType);
        $this->entityManager->persist($email);

        $this->entityManager->flush();
    }

    /**
     * Creates a new Account.
     *
     * @param int $accountType
     *
     * @throws \Exception
     *
     * @return AccountInterface
     */
    public function createAccount($accountType = self::BASIC_TYPE)
    {
        ++$this->accountCount;

        $account = $this->accountFactory->createEntity();
        $account->setName('Account ' . $this->accountCount);
        $account->setPlaceOfJurisdiction('Feldkirch');

        if (method_exists($account, 'setType')) {
            $account->setType($accountType);
        }

        $accountEmail = $this->cloneEntity($this->email);
        $accountEmail->setEmail('company@company.com');
        $account->addEmail($accountEmail);

        $address = $this->createAddress();
        $this->getAccountManager()->addAddress($account, $address, true);

        $this->entityManager->persist($account);
        $this->entityManager->persist($accountEmail);

        return $account;
    }

    /**
     * Creates a new Contact.
     *
     * @return Contact
     */
    public function createContact()
    {
        ++$this->contactCount;
        $contactRepository = $this->container->get('sulu.repository.contact');
        $contact = $contactRepository->createNew();
        $contact->setFirstName('Max');
        $contact->setLastName('Mustermann');
        $contact->setPosition('CEO');
        $contact->setFormOfAddress(1);
        $contact->setSalutation('Sehr geehrter Herr Dr Mustermann');

        $address = $this->createAddress();
        $this->getContactManager()->addAddress($contact, $address, true);

        $email = $this->cloneEntity($this->email);
        $contact->addEmail($email);

        $this->entityManager->persist($contact);

        return $contact;
    }

    /**
     * Creates a contact with a mainAccount.
     *
     * @return Contact
     */
    public function createSupplierContact()
    {
        $contact = $this->createContact();
        $supplier = $this->accountSupplier;

        $this->getAccountManager()->createMainAccountContact($contact, $supplier);

        return $contact;
    }

    /**
     * Creates a new address.
     *
     * @return Address
     */
    public function createAddress()
    {
        $address = new Address();
        $address->setStreet('Musterstraße');
        $address->setNumber('1');
        $address->setZip('0000');
        $address->setCity('Musterstadt');
        $address->setState('Musterland');
        $address->setCountry($this->country);
        $address->setAddressType($this->addressType);
        $address->setBillingAddress(true);
        $address->setPrimaryAddress(true);
        $address->setDeliveryAddress(false);
        $address->setPostboxCity('Dornbirn');
        $address->setPostboxPostcode('6850');
        $address->setPostboxNumber('4711');
        $address->setNote('Note');

        $this->entityManager->persist($address);

        return $address;
    }

    /**
     * @return AccountManager
     */
    public function getAccountManager()
    {
        return $this->container->get('sulu_contact.account_manager');
    }

    /**
     * @return ContactManagerInterface
     */
    public function getContactManager()
    {
        return $this->container->get('sulu_contact.contact_manager');
    }
}
