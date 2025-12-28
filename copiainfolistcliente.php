public static function infolist(Schema $schema): Schema
    {
        return $schema
        ->components([
            Grid::make(3)->schema([
                // Info básica
                Section::make('Datos basicos del cliente')
                ->description('Datos basicos del cliente como nombre, denominacion, razon social y DNI o CIF')
                ->icon('heroicon-o-user')
                    ->schema([
                        TextEntry::make('tipoCliente.nombre')
                            ->label(new HtmlString('<span class="font-semibold">Tipo Cliente</span>'))
                            //->badge()
                            ->color('danger')
                            ->weight('bold'),
                           
    
                        TextEntry::make('nombre')
                        ->label(new HtmlString('<span class="font-semibold">Nombre</span>'))
                            ->copyable()
                            ->weight('bold')
                          
                            ->color('primary'), // <-- Ejemplo de color estático    
                        TextEntry::make('apellidos')
                        ->label(new HtmlString('<span class="font-semibold">Apellidos</span>'))
                            ->copyable()
                            ->weight('bold')
                            ->color('primary'),
                        TextEntry::make('razon_social')
                        ->label(new HtmlString('<span class="font-semibold">Razon Social</span>'))
                            ->copyable()
                            ->weight('bold')
                            ->color('primary')                          
                            ->columnSpan(2),    
                        TextEntry::make('dni_cif')
                        ->label(new HtmlString('<span class="font-semibold">DNI / CIF</span>'))
                            ->copyable()
                            ->weight('bold')
                            ->color('success'),                         
                        TextEntry::make('estado')
                        ->label(new HtmlString('<span class="font-semibold">Estado</span>'))                            
                            ->badge()
                            ->color(fn (ClienteEstadoEnum $state): string => match ($state) { // <-- CAMBIO AQUÍ
                                ClienteEstadoEnum::PENDIENTE, ClienteEstadoEnum::PENDIENTE_ASIGNACION => 'warning',
                                ClienteEstadoEnum::ACTIVO => 'success',
                                ClienteEstadoEnum::IMPAGADO, ClienteEstadoEnum::RESCINDIDO => 'danger',
                                ClienteEstadoEnum::REQUIERE_ATENCION => 'info',
                                default => 'gray',
                            }),
                        TextEntry::make('asesor.name')
                        ->label(new HtmlString('<span class="font-semibold">Asesor</span>'))
                        ->badge()
                        ->getStateUsing(fn ($record) =>
                            $record->asesor
                                ? $record->asesor->name
                                : '⚠️ Sin asignar'
                        )
                        ->color(fn ($state) => str_contains($state, 'Sin asignar') ? 'warning' : 'success'),   
                         
                         TextEntry::make('tarifa_principal_activa_con_precio') // <--- USA EL NUEVO ACCESOR
                        ->label('Tarifa Base')
                        ->placeholder('Ninguna')
                        ->badge() // La insignia se aplicará a toda la cadena "FYCA - 75,00 €"
                        ->tooltip(function ($record) {
                            // El tooltip puede seguir mostrando solo el nombre completo del servicio
                            if ($record->tarifa_principal_activa && $record->tarifa_principal_activa->servicio) {
                                return $record->tarifa_principal_activa->servicio->nombre;
                            }
                            return null; 
                        }),
                                            ])
                    ->columns(3)
                    ->columnSpan(1),

//seccion datos de contacto
                    Section::make('Datos de contacto')
                    ->icon('heroicon-o-phone')
                    ->description('Datos de contacto que tenemos del cliente')
                    ->schema([
                        TextEntry::make('email_contacto')
                        ->label(new HtmlString('<span class="font-semibold">Email</span>'))
                            ->copyable()
                            ->weight('bold')
                            ->color('primary')
                            //->size('lg')
                            ->columnSpan(2), // <-- Ejemplo de color estático
    
                        TextEntry::make('telefono_contacto')
                        ->label(new HtmlString('<span class="font-semibold">Teléfono</span>'))
                            ->copyable()
                            ->weight('bold')
                            ->color('primary'), // <-- Ejemplo de color estático    
                        TextEntry::make('codigo_postal')
                        ->label(new HtmlString('<span class="font-semibold">Código Postal</span>'))
                            ->copyable()
                            ->weight('bold')
                            ->color('primary')
                            ->size('lg'),
                        TextEntry::make('localidad')
                        ->label(new HtmlString('<span class="font-semibold">Localidad</span>'))
                            ->copyable()
                            ->weight('bold')
                            ->color('primary'),    
                        TextEntry::make('provincia')
                        ->label(new HtmlString('<span class="font-semibold">Provincia</span>'))
                            ->copyable()
                            ->weight('bold')
                            ->color('primary'),                      
                        TextEntry::make('direccion')
                        ->label(new HtmlString('<span class="font-semibold">Dirección</span>'))
                            ->copyable()
                            ->weight('bold')
                            ->color('primary')                            
                            ->columnSpan(2),    
                        TextEntry::make('comunidad_autonoma')
                        ->label(new HtmlString('<span class="font-semibold">CCAA</span>'))
                            ->copyable()
                            ->weight('bold')
                            ->color('primary'),         
                                     
                    ])
                    ->columns(3)
                    ->columnSpan(1),

//seccion datos bancarios

                  

                        Tabs::make('Datos cliente')
                        ->tabs([
                             Tab::make('Estado y control')
                            ->schema([
                                TextEntry::make('estado')                           
                                ->label('Estado')
                                ->badge()
                                 ->color(fn (ClienteEstadoEnum $state): string => match ($state) { // <-- CAMBIO 1: Acepta el Enum
                                    // CAMBIO 2: Compara con los casos del Enum, no con texto
                                    ClienteEstadoEnum::PENDIENTE, ClienteEstadoEnum::PENDIENTE_ASIGNACION => 'warning',
                                    ClienteEstadoEnum::ACTIVO => 'success',
                                    ClienteEstadoEnum::IMPAGADO, ClienteEstadoEnum::RESCINDIDO => 'danger',
                                    ClienteEstadoEnum::REQUIERE_ATENCION => 'info',
                                    default => 'gray',
                                }),
                                TextEntry::make('fecha_alta')
                                ->label('Alta servicio')
                                ->copyable()
                                ->weight('bold')
                                ->dateTime('d/m/y - H:m')
                                ->color('primary'),
                                TextEntry::make('fecha_baja')
                                ->label('Baja servicio')
                                ->copyable()
                                ->weight('bold')
                                ->dateTime('d/m/y - H:m')
                                ->color('primary'),
                                TextEntry::make('created_at')
                                ->label('Creado en APP')
                                ->copyable()
                                ->weight('bold')
                                ->dateTime('d/m/y - H:m')
                                ->color('primary'),
                            ])
                            ->columns(4),

                            Tab::make('Datos bancarios')                            
                                ->schema([
                                    TextEntry::make('iban_asesorfy')
                                    ->label(new HtmlString('<span class="font-semibold">Cuenta bancaria cuotas AsesorFy</span>'))                                
                                    ->copyable()
                                    ->weight('bold')
                                    ->state(fn ($record) => $record->iban_asesorfy ?: 'No informado')
                                    ->color(fn (string $state): string =>
                                        $state === 'No informado' ? 'danger' : 'primary'
                                    ),
                                
                                TextEntry::make('iban_impuestos')                                
                                    ->label(new HtmlString('<span class="font-semibold">Cuenta bancaria impuestos</span>')) 
                                    ->copyable()
                                    ->weight('bold')                         
                                    ->state(fn ($record) => $record->iban_impuestos ?: 'No informado')
                                    ->color(fn (string $state): string =>
                                        $state === 'No informado' ? 'danger' : 'primary'
                                    ), 
                                TextEntry::make('ccc')                                
                                    ->label(new HtmlString('<span class="font-semibold">Codigo CCC</span>'))
                                    ->copyable()
                                    ->weight('bold')
                                    ->state(fn ($record) => $record->ccc ?: 'No informado')
                                    ->color(fn (string $state): string =>
                                        $state === 'No informado' ? 'danger' : 'primary'
                                    ),
                                ])->columns(1)
                                ->columnSpan(1),
                       
                            
                        Tab::make('Observaciones generales cliente')
                            ->schema([
                                TextEntry::make('observaciones')
                                ->label('Observaciones internas')
                                ->columnSpanFull(),
                            ]),
                    ])
                    ]), 
        ]);
    }
