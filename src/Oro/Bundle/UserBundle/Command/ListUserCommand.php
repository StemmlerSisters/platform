<?php

declare(strict_types=1);

namespace Oro\Bundle\UserBundle\Command;

use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\EntityExtendBundle\Entity\EnumOption;
use Oro\Bundle\UserBundle\Entity\User;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Lists users.
 */
#[AsCommand(
    name: 'oro:user:list',
    description: 'Lists users.'
)]
class ListUserCommand extends Command
{
    private ManagerRegistry $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
        parent::__construct();
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    #[\Override]
    protected function configure()
    {
        $this
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Also include inactive users')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Limit the number of results (use -1 for all)', 20)
            ->addOption('page', 'p', InputOption::VALUE_REQUIRED, 'Page of the result set', 1)
            ->addOption(
                'roles',
                'r',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Role filter (use ANY for all)',
                []
            )
            ->setHelp(
                <<<'HELP'
The <info>%command.name%</info> command lists users. By default it shows only the first 20 active (enabled) users.

  <info>php %command.full_name%</info>

When the <info>--all</info> option is used it will also list inactive users:

  <info>php %command.full_name% --all</info>

The <info>--limit</info> and <info>--page</info> options control the number of users
to display at once and allow to paginate the results:

  <info>php %command.full_name% --limit=<number> --page=<number></info>

You can list only the users assigned a specific role (or multiple roles)
by using the <info>--role</info> option: 

  <info>php %command.full_name% --roles=<role1> --roles=<role2> --roles=<roleN></info>

HELP
            )
            ->addUsage('--all')
            ->addUsage('--limit=<number> --page=<number>')
            ->addUsage('--roles=<role1> --roles=<role2> --roles=<roleN>')
        ;
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (int) $input->getOption('limit');
        $offset = ((int) $input->getOption('page') - 1) * $limit;

        /** @var QueryBuilder $builder */
        $builder = $this->doctrine
            ->getManagerForClass(User::class)
            ->getRepository(User::class)
            ->createQueryBuilder('u');
        $builder->orderBy('u.enabled', 'DESC')
            ->addOrderBy('u.id', 'ASC');

        if ($offset > 0) {
            $builder->setFirstResult($offset);
        }

        if ($limit > 0) {
            $builder->setMaxResults($limit);
        }

        if (!$input->getOption('all')) {
            $builder
                ->andWhere('u.enabled = :enabled')
                ->setParameter('enabled', true);
        }

        if (!empty($input->getOption('roles'))) {
            $builder
                ->leftJoin('u.userRoles', 'r')
                ->leftJoin(
                    EnumOption::class,
                    'au',
                    Join::WITH,
                    "JSON_EXTRACT(u.serialized_data, 'auth_status') = au"
                )
                ->andWhere('r.label IN (:roles)')
                ->setParameter('roles', $input->getOption('roles'));
        }

        $table = new Table($output);
        $table
            ->setHeaders(['ID', 'Username', 'Enabled (Auth Status)', 'First Name', 'Last Name', 'Roles'])
            ->setRows(array_map([$this, 'getUserRow'], $builder->getQuery()->getResult()))
            ->render();

        return Command::SUCCESS;
    }

    protected function getUserRow(User $user): array
    {
        return [
            $user->getId(),
            $user->getUserIdentifier(),
            sprintf(
                '%s (%s)',
                $user->isEnabled() ? 'Enabled' : 'Disabled',
                $user->getAuthStatus() ? $user->getAuthStatus()->getName() : null
            ),
            $user->getFirstName(),
            $user->getLastName(),
            implode(', ', $user->getUserRoles())
        ];
    }
}
