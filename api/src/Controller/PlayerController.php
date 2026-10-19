<?php

namespace App\Controller;

use App\Dto\PlayerInput;
use App\Entity\Player;
use App\Repository\PlayerRepository;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/players', name: 'api_player_', format: 'json')]
final class PlayerController extends AbstractController
{
    private const READ = ['groups' => ['player:read']];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TeamRepository $teams,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(
        PlayerRepository $players,
        #[MapQueryParameter] ?int $team = null,
        #[MapQueryParameter] ?string $country = null,
        #[MapQueryParameter(options: ['min_range' => 1], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        int $page = 1,

    ): JsonResponse {
        $criteria = [];

        if (null !== $team) {
            $criteria['team'] = $team;
        }

        if (null !== $country) {
            $criteria['country'] = strtoupper($country);
        }

        $list = $players->findBy($criteria, ['nickname' => 'ASC'], 10, ($page - 1) * 10);
        $total = $players->count($criteria) ;
        return $this->json(
            $list,
            Response::HTTP_OK,
            ['X-Total-Count' => (string) $total],
            self::READ,
        );
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => Requirement::DIGITS])]
    public function show(Player $player): JsonResponse
    {
        return $this->json($player, context: self::READ);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(#[MapRequestPayload] PlayerInput $input): JsonResponse
    {
        $player = $this->apply(new Player(), $input);

        $this->em->persist($player);
        $this->em->flush();

        return $this->json(
            $player,
            Response::HTTP_CREATED,
            [
                'Location' => $this->generateUrl('api_player_show', [
                    'id' => $player->getId(),
                ]),
            ],
            self::READ,
        );
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => Requirement::DIGITS])]
    public function update(
        Player $player,
        #[MapRequestPayload] PlayerInput $input,
    ): JsonResponse {
        $this->apply($player, $input);
        $this->em->flush();

        return $this->json($player, context: self::READ);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => Requirement::DIGITS])]
    public function delete(Player $player): Response
    {
        $this->em->remove($player);
        $this->em->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function apply(Player $player, PlayerInput $input): Player
    {
        $player
            ->setNickname($input->nickname)
            ->setCountry(strtoupper($input->country))
            ->setRole($input->role)
            ->setRating($input->rating)
            ->setBirthDate($input->birthDate);

        $team = null;

        if (null !== $input->team) {
            $team = $this->teams->find($input->team);

            if (null === $team) {
                $violations = new ConstraintViolationList([
                    new ConstraintViolation(
                        sprintf('L’équipe n°%d n’existe pas.', $input->team),
                        null,
                        [],
                        $input,
                        'team',
                        $input->team,
                    ),
                ]);

                $this->fail($violations, $input);
            }
        }

        $player->setTeam($team);

        $violations = $this->validator->validate($player);

        if (\count($violations) > 0) {
            $this->fail($violations, $player);
        }

        return $player;
    }

    private function fail(
        ConstraintViolationListInterface $violations,
        object $subject,
    ): never {
        throw new UnprocessableEntityHttpException(
            (string) $violations,
            new ValidationFailedException($subject, $violations),
        );
    }
}
