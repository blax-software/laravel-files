<?php

namespace Blax\Files\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChunkUploadProgress implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $uploadId,
        public int $chunkIndex,
        public int $totalChunks,
        public bool $complete,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel("chunk-upload.{$this->uploadId}")];
    }

    public function broadcastAs(): string
    {
        return 'chunk.progress';
    }
}
