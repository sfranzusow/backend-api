<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

class PropertyManager implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
You are responsible for managing real-estate domain entities.

Current domain structure:
- addresses
- properties
- property_user
- rental_agreements

Rules:
- addresses contain postal address data
- properties belong to one address
- property_user stores contextual user/property roles like landlord, tenant, manager
- rental_agreements represent real rental contracts and are separate from property_user

Important:
- do not model rental agreements only through property_user
- landlord_id and tenant_id in rental_agreements reference users.id
- property-specific relations must remain explicit and normalized
TEXT;
    }

    /**
     * Get the list of messages comprising the conversation so far.
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        return [];
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [];
    }
}
