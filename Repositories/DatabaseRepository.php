<?php

namespace Repositories;

use PDO;
use PDOException;

class DatabaseRepository
{
    private $conn;

    public function __construct()
    {
        $config = require __DIR__ . '/../config/database.php';
        $dbConfig = $config['database'];

        $host = $dbConfig['host'];
        $dbname = $dbConfig['dbname'];
        $user = $dbConfig['user'];
        $password = $dbConfig['password'];

        try {
            $this->conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new PDOException("Erro de conexão com o banco de dados: " . $e->getMessage());
        }
    }

    // Métodos para a tabela pedidos_integracao
    public function inserirPedidoIntegracao(array $data): bool
    {
        $sql = "INSERT INTO pedidos_integracao (
                    protocolo_telenet, 
                    nome_arquivo_telenet, 
                    nome_cliente_telenet, 
                    cnpj_cliente_telenet, 
                    id_deal_bitrix,
                    vinculacao_jallcard
                ) VALUES (
                    :protocolo_telenet, 
                    :nome_arquivo_telenet, 
                    :nome_cliente_telenet, 
                    :cnpj_cliente_telenet, 
                    :id_deal_bitrix,
                    :vinculacao_jallcard
                )";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindValue(':protocolo_telenet', $data['protocolo_telenet']);
        $stmt->bindValue(':nome_arquivo_telenet', $data['nome_arquivo_telenet']);
        $stmt->bindValue(':nome_cliente_telenet', $data['nome_cliente_telenet']);
        $stmt->bindValue(':cnpj_cliente_telenet', $data['cnpj_cliente_telenet']);
        $stmt->bindValue(':id_deal_bitrix', $data['id_deal_bitrix']);
        $stmt->bindValue(':vinculacao_jallcard', $data['vinculacao_jallcard'] ?? 'PENDENTE');

        return $stmt->execute();
    }

    public function getPedidosPendentesVinculacao(): array
    {
        $sql = "SELECT * FROM pedidos_integracao WHERE vinculacao_jallcard = 'PENDENTE'";
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function atualizarVinculacaoJallCard(string $idDealBitrix, string $pedidoProducaoJallCard, string $opJallCard): bool
    {
        $sql = "UPDATE pedidos_integracao SET 
                    pedido_producao_jallcard = :pedido_producao_jallcard, 
                    op_jallcard = :op_jallcard, 
                    vinculacao_jallcard = 'VINCULADO' 
                WHERE id_deal_bitrix = :id_deal_bitrix";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':pedido_producao_jallcard', $pedidoProducaoJallCard);
        $stmt->bindValue(':op_jallcard', $opJallCard);
        $stmt->bindValue(':id_deal_bitrix', $idDealBitrix);
        return $stmt->execute();
    }

    public function atualizarStatusJallCard(string $opJallCard, string $status): bool
    {
        $sql = "UPDATE pedidos_integracao SET status_jallcard = :status WHERE op_jallcard = :op_jallcard";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':op_jallcard', $opJallCard);
        return $stmt->execute();
    }

    public function getPedidosVinculados(): array
    {
        $sql = "SELECT * FROM pedidos_integracao 
                WHERE vinculacao_jallcard = 'VINCULADO' 
                AND status_jallcard NOT IN ('FINALIZADA', 'CANCELADA')";
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Métodos para a tabela vinculacao_jallcard
    public function inserirVinculacaoJallCard(array $data): bool
    {
        $sql = "INSERT INTO vinculacao_jallcard (
                    pedido_producao_jallcard, 
                    op_jallcard, 
                    nome_arquivo_original_jallcard, 
                    nome_arquivo_convertido_jallcard, 
                    data_processamento_jallcard,
                    status_vinculacao_temp
                ) VALUES (
                    :pedido_producao_jallcard, 
                    :op_jallcard, 
                    :nome_arquivo_original_jallcard, 
                    :nome_arquivo_convertido_jallcard, 
                    :data_processamento_jallcard,
                    :status_vinculacao_temp
                )";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindValue(':pedido_producao_jallcard', $data['pedido_producao_jallcard']);
        $stmt->bindValue(':op_jallcard', $data['op_jallcard']);
        $stmt->bindValue(':nome_arquivo_original_jallcard', $data['nome_arquivo_original_jallcard']);
        $stmt->bindValue(':nome_arquivo_convertido_jallcard', $data['nome_arquivo_convertido_jallcard']);
        $stmt->bindValue(':data_processamento_jallcard', $data['data_processamento_jallcard']);
        $stmt->bindValue(':status_vinculacao_temp', $data['status_vinculacao_temp'] ?? 'AGUARDANDO_VINCULO');

        return $stmt->execute();
    }

    public function findVinculacaoJallCardByPedidoProducao(string $pedidoProducaoJallCard): ?array
    {
        $sql = "SELECT * FROM vinculacao_jallcard WHERE pedido_producao_jallcard = :pedido_producao_jallcard LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':pedido_producao_jallcard', $pedidoProducaoJallCard);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function findMatchForBitrixPedido(string $nomeClienteTelenet, string $nomeArquivoTelenet): ?array
    {
        // Lógica de busca para encontrar um match na tabela temporária
        // Prioriza o nome do arquivo original (.TXT.ICS)
        $sql = "SELECT * FROM vinculacao_jallcard 
                WHERE status_vinculacao_temp = 'AGUARDANDO_VINCULO'
                AND (
                    nome_arquivo_original_jallcard LIKE :nome_arquivo_telenet_original OR
                    nome_arquivo_convertido_jallcard LIKE :nome_arquivo_telenet_convertido
                )
                LIMIT 1"; // Limita a 1 para o primeiro match

        $stmt = $this->conn->prepare($sql);
        // Ajuste para a comparação de nomes de arquivo, buscando substrings
        $stmt->bindValue(':nome_arquivo_telenet_original', '%' . $nomeArquivoTelenet . '%');
        $stmt->bindValue(':nome_arquivo_telenet_convertido', '%' . $nomeArquivoTelenet . '%');
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getVinculacoesJallCardPendentes(): array
    {
        $sql = "SELECT * FROM vinculacao_jallcard WHERE status_vinculacao_temp = 'AGUARDANDO_VINCULO'";
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateVinculacaoJallCardStatusTemp(string $pedidoProducaoJallCard, string $status): bool
    {
        $sql = "UPDATE vinculacao_jallcard SET status_vinculacao_temp = :status WHERE pedido_producao_jallcard = :pedido_producao_jallcard";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':pedido_producao_jallcard', $pedidoProducaoJallCard);
        return $stmt->execute();
    }
}
