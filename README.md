# directlabelprinter

Plugin para [GLPI 11](https://glpi-project.org/) que adiciona a ação em massa **"Imprimir Etiqueta"** aos ativos do inventário — funciona tanto para um único item selecionado quanto para vários de uma vez. O plugin monta o PDF da etiqueta internamente (TCPDF) a partir de layouts configuráveis e envia a impressão diretamente para um serviço companheiro (`print_service.py`, externo a este repositório) via HTTP.

## Funcionalidades

- Ação em massa **"Imprimir Etiqueta"** na tela de busca de cada itemtype suportado — disponível mesmo com um único item selecionado.
- Itemtypes suportados: Computadores, Monitores, Dispositivos de rede, Impressoras, Telefones, Periféricos, Racks e Insumos (`Computer`, `Monitor`, `NetworkEquipment`, `Printer`, `Phone`, `Peripheral`, `Rack`, `ConsumableItem` — ver `src/AssetTypes.php::WHITELIST`) — **mais qualquer ativo customizado** criado em Configuração > Definições de ativos (ex.: "Nobreak"), detectado automaticamente, sem precisar editar o plugin.
- Editor de layout via drag-and-drop (GridStack): posicionamento livre de campos de texto e QR Code na etiqueta.
- Suporte a múltiplos servidores de impressão e múltiplos layouts, com um layout padrão por itemtype.
- O plugin lembra o último servidor de impressão usado por cada usuário.
- Suporte a fonte TrueType (`.ttf`) personalizada por layout — a instalação já libera esse tipo de arquivo para upload no GLPI, sem configuração manual.

## Requisitos

- GLPI `11.0.0` até `11.0.99` (ver `PLUGIN_DIRECTLABELPRINTER_MIN_GLPI_VERSION`/`MAX_GLPI_VERSION` em `setup.php`).
- PHP `>= 8.2`.
- Uma instância do `print_service.py` acessível via HTTP, autenticada por `X-API-Key` — esse serviço não faz parte deste repositório e precisa ser instalado/rodando separadamente.

## Instalação

1. Baixe/clone este repositório dentro do diretório `plugins/` da sua instalação GLPI, na pasta `directlabelprinter` (sem a pasta `.git/` em produção):
   ```
   plugins/directlabelprinter/
   ```
2. No GLPI, acesse **Configuração > Plug-ins**, localize "Direct Label Printer" e clique em **Instalar**, depois **Ativar**.

## Configuração básica

Após ativado, o plugin adiciona a entrada **"Config. Imp. Etiquetas"** em **Configuração**, com acesso a:

1. **Servidores de Impressão** — cadastre ao menos um servidor com URL, chave de API e impressora padrão do `print_service.py`.
2. **Layouts** — crie um layout (dimensões, fonte, campos de texto e QR Code), associe-o a um ou mais itemtypes e marque-o como padrão para cada um.

Com um layout padrão configurado para o itemtype, a ação em massa "Imprimir Etiqueta" passa a aparecer na tela de busca correspondente (selecione um ou mais itens > Ações > Imprimir Etiqueta).

## Versionamento e Changelog

O projeto segue [Semantic Versioning](https://semver.org/lang/pt-BR/) e mantém um [`CHANGELOG.md`](CHANGELOG.md) no formato [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/). Os critérios de bump de versão adaptados a este plugin estão em [`docs/versioning.md`](docs/versioning.md).

## Contribuindo

- Todo o trabalho acontece na branch `dev`; `main` reflete apenas o que já foi revisado e mergeado.
- Abra um PR de `dev` para `main` — a descrição deve incluir a seção "Changelog" preenchida a partir do `CHANGELOG.md`.
- Mensagens de commit em inglês, seguindo o padrão `<tipo>(<escopo>): <resumo>`; entradas de changelog e descrições de PR em português.
- Consulte `CLAUDE.md` para as convenções de arquitetura do plugin antes de propor mudanças estruturais.

## Licença

Distribuído sob a licença [MIT](LICENSE).
