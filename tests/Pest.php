<?php

declare(strict_types=1);

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/*
 * Pest configuration for the symfony-hexagonal-starter test suite.
 *
 * Unit tests: use the default Pest base (no framework bootstrap needed — Domain is pure PHP).
 * Integration tests: extend KernelTestCase to boot the Symfony kernel.
 */

// Integration tests (tests/Integration/**) get Symfony kernel support.
uses(KernelTestCase::class)->in('Integration');
