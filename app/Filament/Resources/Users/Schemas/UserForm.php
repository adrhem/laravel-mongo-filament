<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Repeater;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('name'),
                TextInput::make('password')
                    ->password(),
                Repeater::make('addresses')
                    ->schema([
                        TextInput::make('street')->required(),
                        TextInput::make('city')->required(),
                        TextInput::make('state')->required(),
                        TextInput::make('zip_code')->required(),
                    ])
                    ->columnSpanFull()
                    ->columns(2)
            ]);
    }
}
