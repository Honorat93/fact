<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\UserRepository;
use App\Entity\User;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Cache\Adapter\CacheItemPoolInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Entity\Quote;
use App\Repository\QuoteRepository;



class QuoteController extends AbstractController
{
    private $userRepository;
    private $entityManager;
    private $serializer;
    private $passwordEncoder;
    private $jwtManager;
    private $tokenVerifier;
    private $quoteRepository;
    
    public function __construct(
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        UserPasswordHasherInterface $passwordEncoder,
        JWTTokenManagerInterface $jwtManager,
        TokenManagementController $tokenVerifier,
        QuoteRepository $quoteRepository
        
    ) {
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->passwordEncoder = $passwordEncoder;
        $this->jwtManager = $jwtManager;
        $this->tokenVerifier = $tokenVerifier;
        $this->quoteRepository = $quoteRepository;
    }


    #[Route('/quote', name: 'create_quote', methods: ['POST'])]
    public function createQuote(Request $request): JsonResponse
    {
        try {

            $dataMiddellware = $this->tokenVerifier->checkToken($request);
            if (gettype($dataMiddellware) == 'boolean') {
                return $this->json($this->tokenVerifier->sendJsonErrorToken($dataMiddellware), JsonResponse::HTTP_UNAUTHORIZED);
            }
            $user = $dataMiddellware;

            // Récupérer les données du formulaire encodé en URL
            $data = $request->request->all();

            if (empty($data['title']) || empty($data['description']) || empty($data['amount'])) {
                return $this->json([
                    'error' => true,
                    'message' => 'Le titre, la description et le montant sont requis.',
                ], JsonResponse::HTTP_BAD_REQUEST);
            }
            // Récupérer les champs du formulaire
            $title = $data['title'];
            $description = $data['description'];
            $amount = $data['amount'];

            if (!is_numeric($amount)) {
                return $this->json([
                    'error' => true,
                    'message' => 'Le montant doit être un nombre.',
                ], JsonResponse::HTTP_BAD_REQUEST);
            }
    
            // Créer un nouvel objet Quote
            $quote = new Quote();
            $quote->setTitle($title)
                ->setDescription($description)
                ->setAmount($amount)
                ->setCreatedAt(new \DateTime())
                ->setUser($user);

            $this->entityManager->persist($quote);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Devis créé avec succès.',
                'quote' => [
                    'id' => $quote->getId(),
                    'title' => $quote->getTitle(),
                    'description' => $quote->getDescription(),
                    'amount' => $quote->getAmount(),
                    'created_at' => $quote->getCreatedAt()->format('Y-m-d H:i:s'),
                ],
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json([
                'error' => true,
                'message' => 'Erreur lors de la création du devis : ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/quote/{id}', name: 'read_quote', methods: ['GET'])]
    public function readQuote(Request $request, int $id): JsonResponse
    {
        try {
            $dataMiddellware = $this->tokenVerifier->checkToken($request);
            if (gettype($dataMiddellware) == 'boolean') {
                return $this->json($this->tokenVerifier->sendJsonErrorToken($dataMiddellware), JsonResponse::HTTP_UNAUTHORIZED);
            }
    
            $user = $dataMiddellware;
            
            $quote = $this->quoteRepository->find($id);
    
            if (!$quote) {
                return $this->json([
                    'error' => true,
                    'message' => 'Devis non trouvé.',
                ], JsonResponse::HTTP_NOT_FOUND);
            }
    
            return $this->json([
                'success' => true,
                'quote' => [
                    'id' => $quote->getId(),
                    'title' => $quote->getTitle(),
                    'description' => $quote->getDescription(),
                    'amount' => $quote->getAmount(),
                    'created_at' => $quote->getCreatedAt()->format('Y-m-d H:i:s'),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => true,
                'message' => 'Une erreur est survenue lors de la lecture du devis : ' . $e->getMessage(),
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/quote/{id}', name: 'update_quote', methods: ['PUT'])]
public function updateQuote(Request $request, int $id): JsonResponse
{
    try {
        $dataMiddellware = $this->tokenVerifier->checkToken($request);
        if (gettype($dataMiddellware) == 'boolean') {
            return $this->json($this->tokenVerifier->sendJsonErrorToken($dataMiddellware), JsonResponse::HTTP_UNAUTHORIZED);
        }

        $user = $dataMiddellware;

        $quote = $this->quoteRepository->find($id);

        if (!$quote) {
            return $this->json([
                'error' => true,
                'message' => 'Devis non trouvé.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        // Récupérer les données du formulaire encodé en URL
        $data = $request->request->all();

        if (empty($data['title']) || empty($data['description']) || empty($data['amount'])) {
            return $this->json([
                'error' => true,
                'message' => 'Le titre, la description et le montant sont requis.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Mise à jour des données du devis
        $quote->setTitle($data['title'])
              ->setDescription($data['description'])
              ->setAmount($data['amount']);

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Devis mis à jour avec succès.',
            'quote' => [
                'id' => $quote->getId(),
                'title' => $quote->getTitle(),
                'description' => $quote->getDescription(),
                'amount' => $quote->getAmount(),
                'created_at' => $quote->getCreatedAt()->format('Y-m-d H:i:s'),
            ],
        ]);
    } catch (\Exception $e) {
        return $this->json([
            'error' => true,
            'message' => 'Une erreur est survenue lors de la mise à jour du devis : ' . $e->getMessage(),
        ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
    }
}

#[Route('/quote/{id}', name: 'delete_quote', methods: ['DELETE'])]
public function deleteQuote(Request $request, int $id): JsonResponse
{
    try {
        $dataMiddellware = $this->tokenVerifier->checkToken($request);
        if (gettype($dataMiddellware) == 'boolean') {
            return $this->json($this->tokenVerifier->sendJsonErrorToken($dataMiddellware), JsonResponse::HTTP_UNAUTHORIZED);
        }

        $user = $dataMiddellware;

        $quote = $this->quoteRepository->find($id);

        if (!$quote) {
            return $this->json([
                'error' => true,
                'message' => 'Devis non trouvé.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        // Suppression du devis
        $this->entityManager->remove($quote);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Devis supprimé avec succès.',
        ]);
    } catch (\Exception $e) {
        return $this->json([
            'error' => true,
            'message' => 'Une erreur est survenue lors de la suppression du devis : ' . $e->getMessage(),
        ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
    }
}

#[Route('/quotes', name: 'get_all_quotes', methods: ['GET'])]
public function getAllQuotes(Request $request): JsonResponse
{
    try {
        $dataMiddellware = $this->tokenVerifier->checkToken($request);
        if (gettype($dataMiddellware) == 'boolean') {
            return $this->json($this->tokenVerifier->sendJsonErrorToken($dataMiddellware), JsonResponse::HTTP_UNAUTHORIZED);
        }

        $user = $dataMiddellware;

        $quotes = $this->quoteRepository->findAll();

        // Transformation des objets devis en tableau associatif
        $formattedQuotes = [];
        foreach ($quotes as $quote) {
            $formattedQuotes[] = [
                'id' => $quote->getId(),
                'title' => $quote->getTitle(),
                'description' => $quote->getDescription(),
                'amount' => $quote->getAmount(),
                'created_at' => $quote->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        // Réponse JSON avec la liste des devis
        return $this->json([
            'error' => false,
            'quotes' => $formattedQuotes,
        ]);
    } catch (\Exception $e) {
        return $this->json([
            'error' => true,
            'message' => 'Une erreur est survenue lors de la récupération des devis : ' . $e->getMessage(),
        ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
    }
}

} 