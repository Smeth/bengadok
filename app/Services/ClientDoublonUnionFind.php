<?php

namespace App\Services;

/** Union-find minimal pour agréger des relations de doublons clients entre critères (tél, nom, etc.). */
final class ClientDoublonUnionFind
{
    /** @var array<int, int> */
    private array $parent;

    /** @param  list<int|string>  $ids */
    public function __construct(array $ids)
    {
        foreach ($ids as $id) {
            $id = (int) $id;
            $this->parent[$id] = $id;
        }
    }

    public function find(int $id): int
    {
        if (! isset($this->parent[$id])) {
            $this->parent[$id] = $id;
        }
        while ($this->parent[$id] !== $id) {
            $this->parent[$id] = $this->parent[$this->parent[$id]];
            $id = $this->parent[$id];
        }

        return $id;
    }

    public function union(int $a, int $b): void
    {
        $ra = $this->find($a);
        $rb = $this->find($b);
        if ($ra !== $rb) {
            $this->parent[$rb] = $ra;
        }
    }
}
