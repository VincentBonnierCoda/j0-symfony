<?php

namespace App\Controller;

use App\Entity\Team;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/teams', name: 'api_team_', format: 'json')]
final class TeamController extends AbstractController
{
    private const READ = ['groups' => ['team:read']];
    private const WRITE = ['groups' => ['team:write']];

    public function __construct(
        private readonly EntityManagerInterface $em,
    )
    {
    }

    /** GET /api/teams */
    #[Route('', name: 'list', methods: ['GET'], )]

    public function list(TeamRepository $teams): JsonResponse
    {

        return $this->json($teams->findAll(), context: self::READ);
    }

    /** GET /api/teams/{id} */
    #[Route(
        '/{id}',
        name: 'show',
        methods: ['GET'],
        requirements: ['id' => Requirement::DIGITS],
    )]
    #[OA\Response(
        response: 200,
        description: 'Équipe demandée',
        content: new Model(type: Team::class, groups: ['team:read']),
    )]
    #[OA\Response(response: 404, description: 'Équipe introuvable')]
    public function show(Team $team): JsonResponse
    {
        return $this->json($team, context: self::READ);
    }

    /** POST /api/teams */
    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Response(
        response: 201,
        description: 'Équipe créée ; son URL est dans l’en-tête Location',
    )]
    #[OA\Response(response: 422, description: 'Données invalides')]
    public function create(
        #[MapRequestPayload(
            acceptFormat: 'json',
            serializationContext: self::WRITE,
        )]
        Team $team,
    ): JsonResponse
    {
        $this->em->persist($team);
        $this->em->flush();

        return $this->json(
            $team,
            Response::HTTP_CREATED,
            [
                'Location' => $this->generateUrl('api_team_show', [
                    'id' => $team->getId(),
                ]),
            ],
            self::READ,
        );
    }

    /** PATCH /api/teams/{id} */
    #[Route(
        '/{id}',
        name: 'update',
        methods: ['PATCH'],
        requirements: ['id' => Requirement::DIGITS],
    )]
    public function update(
        Team                $team,
        Request             $request,
        SerializerInterface $serializer,
        ValidatorInterface  $validator,
    ): JsonResponse
    {
        try {
            $serializer->deserialize(
                $request->getContent(),
                Team::class,
                'json',
                [
                    AbstractNormalizer::OBJECT_TO_POPULATE => $team,
                    ...self::WRITE,
                ],
            );
        } catch (SerializerException $e) {
            throw new BadRequestHttpException(
                'Corps de requête invalide : ' . $e->getMessage(),
                $e,
            );
        }

        $violations = $validator->validate($team);

        if (\count($violations) > 0) {
            throw new UnprocessableEntityHttpException(
                (string)$violations,
                new ValidationFailedException($team, $violations),
            );
        }

        $this->em->flush();

        return $this->json($team, context: self::READ);
    }

    /** DELETE /api/teams/{id} */
    #[Route(
        '/{id}',
        name: 'delete',
        methods: ['DELETE'],
        requirements: ['id' => Requirement::DIGITS],
    )]
    public function delete(Team $team): Response
    {
        $this->em->remove($team);
        $this->em->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
