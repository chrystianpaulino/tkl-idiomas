@php($contextName = $schoolName ?? $platformName)
<x-mail::message>
# Olá, {{ $user->name }}!

Você foi convidado por **{{ $invitedBy->name }}** para acessar o {{ $contextName }} como **{{ \App\Support\RoleLabels::for($user->role) }}**.

Clique no botão abaixo para definir sua senha e acessar a plataforma.

<x-mail::button :url="$acceptUrl" color="primary">
Definir minha senha
</x-mail::button>

Este link é pessoal, único e expira em **7 dias**. Se você não esperava este convite, pode ignorar este email — nenhuma conta será ativada sem que você defina uma senha.

Se o botão não funcionar, copie e cole este endereço no navegador:

[{{ $acceptUrl }}]({{ $acceptUrl }})

Bem-vindo,<br>
Equipe {{ $platformName }}
</x-mail::message>
