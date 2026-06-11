<?php

namespace App\Filament\Resources\MailDomainResource\RelationManagers;

use App\Models\AuditLog;
use App\Services\PanelCtl;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MailboxesRelationManager extends RelationManager
{
    protected static string $relationship = 'mailboxes';
    protected static ?string $title = 'Mailboxes';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('local_part')
                ->label('Mailbox')
                ->required()
                ->regex('/^[a-z0-9._+-]{1,64}$/')
                ->suffix('@' . $this->getOwnerRecord()->domain),
            Forms\Components\TextInput::make('password')
                ->password()
                ->revealable()
                ->minLength(10)
                ->helperText('Leave blank to auto-generate a password.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('address')
            ->columns([
                Tables\Columns\TextColumn::make('address')->searchable(),
                Tables\Columns\TextColumn::make('created_at')->date()->label('Created'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->using(function (array $data): Model {
                        $domain = $this->getOwnerRecord();
                        $address = strtolower($data['local_part']) . '@' . $domain->domain;
                        $generated = empty($data['password']);
                        $password = $generated ? Str::password(16, symbols: false) : $data['password'];

                        $result = app(PanelCtl::class)->run(
                            'mail:mailbox:add',
                            ['address' => $address],
                            $password . "\n",
                        );
                        if (!$result->ok()) {
                            Notification::make()->title('mailbox add failed')->body($result->output())
                                ->danger()->persistent()->send();
                            $this->halt();
                        }

                        AuditLog::record('mail.mailbox.add', $address);
                        if ($generated) {
                            Notification::make()->title("Mailbox {$address} created")
                                ->body("Password: {$password} — save it now.")->success()->persistent()->send();
                        }

                        return $domain->mailboxes()->create(['address' => $address]);
                    }),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('No mailboxes yet');
    }
}
