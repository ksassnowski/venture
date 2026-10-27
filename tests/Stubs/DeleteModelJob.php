<?php

declare(strict_types=1);

/**
 * Copyright (c) 2023 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/ksassnowski/venture
 */

namespace Stubs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;

class DeleteModelJob implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Model $model)
    {
    }

    public function handle(): void
    {
        $this->model->delete();
    }
}
