<?php

namespace App\Console\Commands;

use App\Support\ProcessingRuntimeConfigValidator;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class CutoverCheckCommand extends Command
{
    protected $signature = 'docintern:cutover-check';

    protected $description = 'Validate unified development runtime configuration contracts';

    public function __construct(
        public ProcessingRuntimeConfigValidator $processingRuntimeConfigValidator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->line('Runtime profile: development-first');

        try {
            $this->processingRuntimeConfigValidator->validateOrFail();
        } catch (InvalidArgumentException $exception) {
            $this->error('Environment contract check failed.');
            $this->line($exception->getMessage());

            return SymfonyCommand::FAILURE;
        }

        $this->info('Environment contract check passed.');

        return SymfonyCommand::SUCCESS;
    }
}
