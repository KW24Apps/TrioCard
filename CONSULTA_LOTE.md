# CONSULTA EM LOTE FLASHPEGASUS

## CONSULTA EM LOTE
HTTP CODE: **200**
Payload enviado:
```
{"clienteId":5917,"cttId":[14913],"numEncCli":["2510840126001","2510847134001","2510847141001","2510848135001","2510848137001","2510848140001","2510848142001","2510850129001","2510850132001","2510850136001","2510850138001","2510856128001","2510856130001","2510856139001","2510857131001","2510858133001","2510859125001","2510866127001"]}
```
Resposta:
```
{
  "statusRetorno": "00",
  "hawbs": [
    {
      "codigoCartao": "2510859125001",
      "meuNumero": "",
      "hawbId": "08048425023",
      "dtCol": "10/09/2025 09:39:03.000",
      "dtPost": "10/09/2025 10:06:29.924",
      "dtSla": "16/09/2025 23:59:59.000",
      "contrato": "14913",
      "baixa": [
        {
          "grauParentesco": "funcionário",
          "recebedor": "rodrigo goes",
          "rg": "09817262910",
          "dtBaixa": "12/09/2025 17:51:31.000",
          "tentativas": "1"
        }
      ],
      "historico": [
        {
          "ocorrencia": "09/09/2025 06:52:32.000",
          "eventoId": "1100",
          "evento": "Em arquivo-aguardando Postagem",
          "arCorreios": "",
          "frq": "SAO",
          "local": "Sao Bernardo do Campo"
        },
        {
          "ocorrencia": "10/09/2025 10:06:30.490",
          "eventoId": "1400",
          "evento": "Postado - logistica iniciada",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "10/09/2025 15:02:36.036",
          "eventoId": "2000",
          "evento": "Preparada para a transferencia",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "11/09/2025 12:42:10.014",
          "eventoId": "3000",
          "evento": "OBJETO Recebido",
          "arCorreios": "",
          "frq": "FPL",
          "local": "Palhoça"
        },
        {
          "ocorrencia": "12/09/2025 09:34:49.195",
          "eventoId": "4100",
          "evento": "Entrega em andamento (na rua)",
          "arCorreios": "",
          "frq": "FPL",
          "local": "Palhoça"
        },
        {
          "ocorrencia": "12/09/2025 17:53:25.258",
          "eventoId": "4250",
          "evento": "Entrega registrada via RT",
          "arCorreios": "",
          "frq": "FPL",
          "local": "Palhoça"
        },
        {
          "ocorrencia": "12/09/2025 21:49:49.810",
          "eventoId": "4300",
          "evento": "Entrega registrada",
          "arCorreios": "",
          "frq": "FPL",
          "local": "Palhoça"
        },
        {
          "ocorrencia": "12/09/2025 21:49:51.442",
          "eventoId": "5000",
          "evento": "Comprovante registrado",
          "arCorreios": "",
          "frq": "FPL",
          "local": "Palhoça"
        }
      ]
    },
    {
      "codigoCartao": "2510840126001",
      "meuNumero": "",
      "hawbId": "08049666471",
      "dtCol": "10/09/2025 09:39:03.000",
      "dtPost": "10/09/2025 10:06:39.512",
      "dtSla": "17/09/2025 23:59:59.000",
      "contrato": "14913",
      "baixa": [
        {
          "grauParentesco": "Próprio",
          "recebedor": "luciane zeckoski",
          "rg": "04173079931",
          "dtBaixa": "16/09/2025 11:49:49.000",
          "tentativas": "2"
        }
      ],
      "historico": [
        {
          "ocorrencia": "09/09/2025 11:06:49.000",
          "eventoId": "1100",
          "evento": "Em arquivo-aguardando Postagem",
          "arCorreios": "",
          "frq": "SAO",
          "local": "Sao Bernardo do Campo"
        },
        {
          "ocorrencia": "10/09/2025 10:06:40.226",
          "eventoId": "1400",
          "evento": "Postado - logistica iniciada",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "10/09/2025 15:12:11.369",
          "eventoId": "2000",
          "evento": "Preparada para a transferencia",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "11/09/2025 12:34:57.266",
          "eventoId": "3000",
          "evento": "OBJETO Recebido",
          "arCorreios": "",
          "frq": "FRP",
          "local": "São José"
        },
        {
          "ocorrencia": "12/09/2025 12:08:37.985",
          "eventoId": "4100",
          "evento": "Entrega em andamento (na rua)",
          "arCorreios": "",
          "frq": "FRP",
          "local": "São José"
        },
        {
          "ocorrencia": "12/09/2025 13:12:26.402",
          "eventoId": "4255",
          "evento": "Entrega NAO efetuada(RT)",
          "arCorreios": "",
          "frq": "FRP",
          "local": "São José",
          "situacao": "Ausente",
          "situacaoId": "600"
        },
        {
          "ocorrencia": "12/09/2025 16:47:13.025",
          "eventoId": "4200",
          "evento": "Entrega NAO efetuada",
          "arCorreios": "",
          "frq": "FRP",
          "local": "São José",
          "situacao": "Ausente",
          "situacaoId": "600"
        },
        {
          "ocorrencia": "16/09/2025 11:05:35.749",
          "eventoId": "4100",
          "evento": "Entrega em andamento (na rua)",
          "arCorreios": "",
          "frq": "FRP",
          "local": "São José"
        },
        {
          "ocorrencia": "16/09/2025 11:54:50.243",
          "eventoId": "4250",
          "evento": "Entrega registrada via RT",
          "arCorreios": "",
          "frq": "FRP",
          "local": "São José"
        },
        {
          "ocorrencia": "16/09/2025 19:06:28.804",
          "eventoId": "4300",
          "evento": "Entrega registrada",
          "arCorreios": "",
          "frq": "FRP",
          "local": "São José"
        },
        {
          "ocorrencia": "16/09/2025 19:06:31.267",
          "eventoId": "5000",
          "evento": "Comprovante registrado",
          "arCorreios": "",
          "frq": "FRP",
          "local": "São José"
        }
      ]
    },
    {
      "codigoCartao": "2510866127001",
      "meuNumero": "",
      "hawbId": "08053690910",
      "dtCol": "12/09/2025 10:50:18.000",
      "dtPost": "12/09/2025 10:55:06.891",
      "dtSla": "18/09/2025 23:59:59.000",
      "contrato": "14913",
      "baixa": [
        {
          "grauParentesco": "port",
          "recebedor": "karla tavares",
          "rg": "23990777",
          "dtBaixa": "15/09/2025 17:29:34.000",
          "tentativas": "1"
        }
      ],
      "historico": [
        {
          "ocorrencia": "10/09/2025 06:54:32.000",
          "eventoId": "1100",
          "evento": "Em arquivo-aguardando Postagem",
          "arCorreios": "",
          "frq": "SAO",
          "local": "Sao Bernardo do Campo"
        },
        {
          "ocorrencia": "12/09/2025 10:55:07.435",
          "eventoId": "1400",
          "evento": "Postado - logistica iniciada",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "12/09/2025 14:39:37.694",
          "eventoId": "2000",
          "evento": "Preparada para a transferencia",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "13/09/2025 19:38:55.623",
          "eventoId": "3000",
          "evento": "OBJETO Recebido",
          "arCorreios": "",
          "frq": "FRS",
          "local": "PALHOÇA"
        },
        {
          "ocorrencia": "15/09/2025 10:00:09.288",
          "eventoId": "4100",
          "evento": "Entrega em andamento (na rua)",
          "arCorreios": "",
          "frq": "FRS",
          "local": "PALHOÇA"
        },
        {
          "ocorrencia": "15/09/2025 17:35:30.062",
          "eventoId": "4250",
          "evento": "Entrega registrada via RT",
          "arCorreios": "",
          "frq": "FRS",
          "local": "PALHOÇA"
        },
        {
          "ocorrencia": "15/09/2025 20:00:51.952",
          "eventoId": "4300",
          "evento": "Entrega registrada",
          "arCorreios": "",
          "frq": "FRS",
          "local": "PALHOÇA"
        },
        {
          "ocorrencia": "15/09/2025 20:00:59.173",
          "eventoId": "5000",
          "evento": "Comprovante registrado",
          "arCorreios": "",
          "frq": "FRS",
          "local": "PALHOÇA"
        }
      ]
    },
    {
      "codigoCartao": "2510856128001",
      "meuNumero": "",
      "hawbId": "08054709376",
      "dtCol": "12/09/2025 10:50:18.000",
      "dtPost": "12/09/2025 10:55:05.425",
      "dtSla": "18/09/2025 23:59:59.000",
      "contrato": "14913",
      "baixa": [
        {
          "grauParentesco": "port",
          "recebedor": "Mayara  Mira",
          "rg": "90717660206",
          "dtBaixa": "15/09/2025 11:12:27.000",
          "tentativas": "1"
        }
      ],
      "historico": [
        {
          "ocorrencia": "10/09/2025 10:47:54.000",
          "eventoId": "1100",
          "evento": "Em arquivo-aguardando Postagem",
          "arCorreios": "",
          "frq": "SAO",
          "local": "Sao Bernardo do Campo"
        },
        {
          "ocorrencia": "12/09/2025 10:55:05.976",
          "eventoId": "1400",
          "evento": "Postado - logistica iniciada",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "12/09/2025 14:06:43.792",
          "eventoId": "2000",
          "evento": "Preparada para a transferencia",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "14/09/2025 11:06:53.173",
          "eventoId": "3000",
          "evento": "OBJETO Recebido",
          "arCorreios": "",
          "frq": "JVL",
          "local": "Joinville"
        },
        {
          "ocorrencia": "15/09/2025 07:46:41.597",
          "eventoId": "4100",
          "evento": "Entrega em andamento (na rua)",
          "arCorreios": "",
          "frq": "JVL",
          "local": "Joinville"
        },
        {
          "ocorrencia": "15/09/2025 14:44:27.333",
          "eventoId": "4250",
          "evento": "Entrega registrada via RT",
          "arCorreios": "",
          "frq": "JVL",
          "local": "Joinville"
        },
        {
          "ocorrencia": "15/09/2025 17:46:14.112",
          "eventoId": "4300",
          "evento": "Entrega registrada",
          "arCorreios": "",
          "frq": "JVL",
          "local": "Joinville"
        },
        {
          "ocorrencia": "15/09/2025 17:46:17.064",
          "eventoId": "5000",
          "evento": "Comprovante registrado",
          "arCorreios": "",
          "frq": "JVL",
          "local": "Joinville"
        }
      ]
    },
    {
      "codigoCartao": "2510857131001",
      "meuNumero": "",
      "hawbId": "08058524657",
      "dtCol": "12/09/2025 10:15:42.000",
      "dtPost": "12/09/2025 11:25:10.089",
      "dtSla": "18/09/2025 23:59:59.000",
      "contrato": "14913",
      "baixa": [
        {
          "grauParentesco": "port",
          "recebedor": "Mayara Mira",
          "rg": "90717660206",
          "dtBaixa": "15/09/2025 11:13:19.000",
          "tentativas": "1"
        }
      ],
      "historico": [
        {
          "ocorrencia": "11/09/2025 06:52:55.000",
          "eventoId": "1100",
          "evento": "Em arquivo-aguardando Postagem",
          "arCorreios": "",
          "frq": "SAO",
          "local": "Sao Bernardo do Campo"
        },
        {
          "ocorrencia": "12/09/2025 11:25:11.257",
          "eventoId": "1400",
          "evento": "Postado - logistica iniciada",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "12/09/2025 14:06:43.792",
          "eventoId": "2000",
          "evento": "Preparada para a transferencia",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "14/09/2025 11:11:28.141",
          "eventoId": "3000",
          "evento": "OBJETO Recebido",
          "arCorreios": "",
          "frq": "JVL",
          "local": "Joinville"
        },
        {
          "ocorrencia": "15/09/2025 07:46:41.597",
          "eventoId": "4100",
          "evento": "Entrega em andamento (na rua)",
          "arCorreios": "",
          "frq": "JVL",
          "local": "Joinville"
        },
        {
          "ocorrencia": "15/09/2025 14:44:25.941",
          "eventoId": "4250",
          "evento": "Entrega registrada via RT",
          "arCorreios": "",
          "frq": "JVL",
          "local": "Joinville"
        },
        {
          "ocorrencia": "15/09/2025 17:46:14.112",
          "eventoId": "4300",
          "evento": "Entrega registrada",
          "arCorreios": "",
          "frq": "JVL",
          "local": "Joinville"
        },
        {
          "ocorrencia": "15/09/2025 17:46:17.064",
          "eventoId": "5000",
          "evento": "Comprovante registrado",
          "arCorreios": "",
          "frq": "JVL",
          "local": "Joinville"
        }
      ]
    },
    {
      "codigoCartao": "2510850129001",
      "meuNumero": "",
      "hawbId": "08058524668",
      "dtCol": "12/09/2025 10:15:42.000",
      "dtPost": "12/09/2025 11:25:12.858",
      "dtSla": "18/09/2025 23:59:59.000",
      "contrato": "14913",
      "baixa": [
        {
          "grauParentesco": "funcionário",
          "recebedor": "Allan  zapora",
          "rg": "13914866916",
          "dtBaixa": "15/09/2025 09:16:40.000",
          "tentativas": "1"
        }
      ],
      "historico": [
        {
          "ocorrencia": "11/09/2025 06:53:01.000",
          "eventoId": "1100",
          "evento": "Em arquivo-aguardando Postagem",
          "arCorreios": "",
          "frq": "SAO",
          "local": "Sao Bernardo do Campo"
        },
        {
          "ocorrencia": "12/09/2025 11:25:13.797",
          "eventoId": "1400",
          "evento": "Postado - logistica iniciada",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "12/09/2025 13:53:39.378",
          "eventoId": "2000",
          "evento": "Preparada para a transferencia",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "13/09/2025 06:29:19.164",
          "eventoId": "3000",
          "evento": "OBJETO Recebido",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "15/09/2025 08:47:20.104",
          "eventoId": "4100",
          "evento": "Entrega em andamento (na rua)",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "15/09/2025 09:18:50.261",
          "eventoId": "4250",
          "evento": "Entrega registrada via RT",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "15/09/2025 12:55:21.989",
          "eventoId": "4300",
          "evento": "Entrega registrada",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "15/09/2025 12:55:25.089",
          "eventoId": "5000",
          "evento": "Comprovante registrado",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        }
      ]
    },
    {
      "codigoCartao": "2510856130001",
      "meuNumero": "",
      "hawbId": "08058524679",
      "dtCol": "12/09/2025 10:15:42.000",
      "dtPost": "12/09/2025 11:25:15.672",
      "dtSla": "18/09/2025 23:59:59.000",
      "contrato": "14913",
      "baixa": [
        {
          "grauParentesco": "port",
          "recebedor": "Mayara  mira",
          "rg": "90717660206",
          "dtBaixa": "15/09/2025 11:11:38.000",
          "tentativas": "1"
        }
      ],
      "historico": [
        {
          "ocorrencia": "11/09/2025 06:53:07.000",
          "eventoId": "1100",
          "evento": "Em arquivo-aguardando Postagem",
          "arCorreios": "",
          "frq": "SAO",
          "local": "Sao Bernardo do Campo"
        },
        {
          "ocorrencia": "12/09/2025 11:25:16.215",
          "eventoId": "1400",
          "evento": "Postado - logistica iniciada",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "12/09/2025 14:06:43.792",
          "eventoId": "2000",
          "evento": "Preparada para a transferencia",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "14/09/2025 11:12:27.994",
          "eventoId": "3000",
          "evento": "OBJETO Recebido",
          "arCorreios": "",
          "frq": "JVL",
          "local": "Joinville"
        },
        {
          "ocorrencia": "15/09/2025 07:46:41.597",
          "eventoId": "4100",
          "evento": "Entrega em andamento (na rua)",
          "arCorreios": "",
          "frq": "JVL",
          "local": "Joinville"
        },
        {
          "ocorrencia": "15/09/2025 14:44:25.925",
          "eventoId": "4250",
          "evento": "Entrega registrada via RT",
          "arCorreios": "",
          "frq": "JVL",
          "local": "Joinville"
        },
        {
          "ocorrencia": "15/09/2025 17:46:14.112",
          "eventoId": "4300",
          "evento": "Entrega registrada",
          "arCorreios": "",
          "frq": "JVL",
          "local": "Joinville"
        },
        {
          "ocorrencia": "15/09/2025 17:46:17.064",
          "eventoId": "5000",
          "evento": "Comprovante registrado",
          "arCorreios": "",
          "frq": "JVL",
          "local": "Joinville"
        }
      ]
    },
    {
      "codigoCartao": "2510850132001",
      "meuNumero": "",
      "hawbId": "08063263856",
      "dtCol": "15/09/2025 09:17:22.000",
      "dtPost": "15/09/2025 09:28:36.722",
      "dtSla": "19/09/2025 23:59:59.000",
      "contrato": "14913",
      "baixa": [
        {
          "grauParentesco": "funcionário",
          "recebedor": "Allan  zapora",
          "rg": "13914866926",
          "dtBaixa": "16/09/2025 09:44:00.000",
          "tentativas": "1"
        }
      ],
      "historico": [
        {
          "ocorrencia": "12/09/2025 06:55:52.000",
          "eventoId": "1100",
          "evento": "Em arquivo-aguardando Postagem",
          "arCorreios": "",
          "frq": "SAO",
          "local": "Sao Bernardo do Campo"
        },
        {
          "ocorrencia": "15/09/2025 09:28:37.274",
          "eventoId": "1400",
          "evento": "Postado - logistica iniciada",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "15/09/2025 15:01:32.382",
          "eventoId": "2000",
          "evento": "Preparada para a transferencia",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "16/09/2025 06:37:27.561",
          "eventoId": "3000",
          "evento": "OBJETO Recebido",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "16/09/2025 08:58:10.982",
          "eventoId": "4100",
          "evento": "Entrega em andamento (na rua)",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "16/09/2025 09:44:50.840",
          "eventoId": "4250",
          "evento": "Entrega registrada via RT",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "16/09/2025 12:41:58.896",
          "eventoId": "4300",
          "evento": "Entrega registrada",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "16/09/2025 12:41:59.749",
          "eventoId": "5000",
          "evento": "Comprovante registrado",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        }
      ]
    },
    {
      "codigoCartao": "2510858133001",
      "meuNumero": "",
      "hawbId": "08063263903",
      "dtCol": "15/09/2025 09:17:22.000",
      "dtPost": "15/09/2025 09:25:11.559",
      "dtSla": "19/09/2025 23:59:59.000",
      "contrato": "14913",
      "baixa": [
        {
          "grauParentesco": "funcionário",
          "recebedor": "Suzana Truz",
          "rg": "04517847900",
          "dtBaixa": "18/09/2025 12:27:53.000",
          "tentativas": "1"
        }
      ],
      "historico": [
        {
          "ocorrencia": "12/09/2025 06:55:58.000",
          "eventoId": "1100",
          "evento": "Em arquivo-aguardando Postagem",
          "arCorreios": "",
          "frq": "SAO",
          "local": "Sao Bernardo do Campo"
        },
        {
          "ocorrencia": "15/09/2025 09:25:12.331",
          "eventoId": "1400",
          "evento": "Postado - logistica iniciada",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "15/09/2025 15:11:36.383",
          "eventoId": "2000",
          "evento": "Preparada para a transferencia",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "16/09/2025 13:55:12.962",
          "eventoId": "3000",
          "evento": "OBJETO Recebido",
          "arCorreios": "",
          "frq": "FPL",
          "local": "Palhoça"
        },
        {
          "ocorrencia": "18/09/2025 08:54:22.857",
          "eventoId": "4100",
          "evento": "Entrega em andamento (na rua)",
          "arCorreios": "",
          "frq": "FPL",
          "local": "Palhoça"
        },
        {
          "ocorrencia": "18/09/2025 12:29:25.429",
          "eventoId": "4250",
          "evento": "Entrega registrada via RT",
          "arCorreios": "",
          "frq": "FPL",
          "local": "Palhoça"
        },
        {
          "ocorrencia": "18/09/2025 18:22:09.904",
          "eventoId": "4300",
          "evento": "Entrega registrada",
          "arCorreios": "",
          "frq": "FPL",
          "local": "Palhoça"
        },
        {
          "ocorrencia": "18/09/2025 18:22:10.873",
          "eventoId": "5000",
          "evento": "Comprovante registrado",
          "arCorreios": "",
          "frq": "FPL",
          "local": "Palhoça"
        }
      ]
    },
    {
      "codigoCartao": "2510848135001",
      "meuNumero": "",
      "hawbId": "08064035663",
      "dtCol": "15/09/2025 09:17:22.000",
      "dtPost": "15/09/2025 09:28:38.238",
      "dtSla": "19/09/2025 23:59:59.000",
      "contrato": "14913",
      "baixa": [
        {
          "grauParentesco": "funcionário",
          "recebedor": "Allan  zapora",
          "rg": "13914866926",
          "dtBaixa": "16/09/2025 09:43:18.000",
          "tentativas": "1"
        }
      ],
      "historico": [
        {
          "ocorrencia": "12/09/2025 11:09:08.000",
          "eventoId": "1100",
          "evento": "Em arquivo-aguardando Postagem",
          "arCorreios": "",
          "frq": "SAO",
          "local": "Sao Bernardo do Campo"
        },
        {
          "ocorrencia": "15/09/2025 09:28:38.850",
          "eventoId": "1400",
          "evento": "Postado - logistica iniciada",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "15/09/2025 15:01:32.382",
          "eventoId": "2000",
          "evento": "Preparada para a transferencia",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "16/09/2025 06:37:32.955",
          "eventoId": "3000",
          "evento": "OBJETO Recebido",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "16/09/2025 08:58:10.982",
          "eventoId": "4100",
          "evento": "Entrega em andamento (na rua)",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "16/09/2025 09:44:50.786",
          "eventoId": "4250",
          "evento": "Entrega registrada via RT",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "16/09/2025 12:41:58.896",
          "eventoId": "4300",
          "evento": "Entrega registrada",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "16/09/2025 12:41:59.749",
          "eventoId": "5000",
          "evento": "Comprovante registrado",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        }
      ]
    },
    {
      "codigoCartao": "2510847134001",
      "meuNumero": "",
      "hawbId": "08064035721",
      "dtCol": "15/09/2025 09:17:22.000",
      "dtPost": "15/09/2025 09:28:42.614",
      "dtSla": "19/09/2025 23:59:59.000",
      "contrato": "14913",
      "baixa": [
        {
          "grauParentesco": "funcionário",
          "recebedor": "Allan  zapora",
          "rg": "13914866926",
          "dtBaixa": "16/09/2025 09:43:39.000",
          "tentativas": "1"
        }
      ],
      "historico": [
        {
          "ocorrencia": "12/09/2025 11:09:19.000",
          "eventoId": "1100",
          "evento": "Em arquivo-aguardando Postagem",
          "arCorreios": "",
          "frq": "SAO",
          "local": "Sao Bernardo do Campo"
        },
        {
          "ocorrencia": "15/09/2025 09:28:43.182",
          "eventoId": "1400",
          "evento": "Postado - logistica iniciada",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "15/09/2025 15:01:32.382",
          "eventoId": "2000",
          "evento": "Preparada para a transferencia",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "16/09/2025 06:37:32.302",
          "eventoId": "3000",
          "evento": "OBJETO Recebido",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "16/09/2025 08:58:10.982",
          "eventoId": "4100",
          "evento": "Entrega em andamento (na rua)",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "16/09/2025 09:44:50.816",
          "eventoId": "4250",
          "evento": "Entrega registrada via RT",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "16/09/2025 12:41:58.896",
          "eventoId": "4300",
          "evento": "Entrega registrada",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "16/09/2025 12:41:59.749",
          "eventoId": "5000",
          "evento": "Comprovante registrado",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        }
      ]
    },
    {
      "codigoCartao": "2510850136001",
      "meuNumero": "",
      "hawbId": "08064035776",
      "dtCol": "15/09/2025 09:17:22.000",
      "dtPost": "15/09/2025 09:28:40.080",
      "dtSla": "19/09/2025 23:59:59.000",
      "contrato": "14913",
      "baixa": [
        {
          "grauParentesco": "funcionário",
          "recebedor": "Allan  zapora",
          "rg": "13914866926",
          "dtBaixa": "16/09/2025 09:42:55.000",
          "tentativas": "1"
        }
      ],
      "historico": [
        {
          "ocorrencia": "12/09/2025 11:09:30.000",
          "eventoId": "1100",
          "evento": "Em arquivo-aguardando Postagem",
          "arCorreios": "",
          "frq": "SAO",
          "local": "Sao Bernardo do Campo"
        },
        {
          "ocorrencia": "15/09/2025 09:28:40.648",
          "eventoId": "1400",
          "evento": "Postado - logistica iniciada",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "15/09/2025 15:01:32.382",
          "eventoId": "2000",
          "evento": "Preparada para a transferencia",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "16/09/2025 06:37:28.914",
          "eventoId": "3000",
          "evento": "OBJETO Recebido",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "16/09/2025 08:58:10.982",
          "eventoId": "4100",
          "evento": "Entrega em andamento (na rua)",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "16/09/2025 09:44:50.872",
          "eventoId": "4250",
          "evento": "Entrega registrada via RT",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "16/09/2025 12:41:58.896",
          "eventoId": "4300",
          "evento": "Entrega registrada",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "16/09/2025 12:41:59.749",
          "eventoId": "5000",
          "evento": "Comprovante registrado",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        }
      ]
    },
    {
      "codigoCartao": "2510848137001",
      "meuNumero": "",
      "hawbId": "08070292503",
      "dtCol": "16/09/2025 15:00:15.000",
      "dtPost": "16/09/2025 15:00:25.081",
      "dtSla": "22/09/2025 23:59:59.000",
      "contrato": "14913",
      "baixa": [
        {
          "grauParentesco": "funcionário",
          "recebedor": "roselei bueno",
          "rg": "85639552",
          "dtBaixa": "17/09/2025 09:42:43.000",
          "tentativas": "1"
        }
      ],
      "historico": [
        {
          "ocorrencia": "15/09/2025 06:51:28.000",
          "eventoId": "1100",
          "evento": "Em arquivo-aguardando Postagem",
          "arCorreios": "",
          "frq": "SAO",
          "local": "Sao Bernardo do Campo"
        },
        {
          "ocorrencia": "16/09/2025 15:00:25.653",
          "eventoId": "1400",
          "evento": "Postado - logistica iniciada",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "16/09/2025 15:39:06.953",
          "eventoId": "2000",
          "evento": "Preparada para a transferencia",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "17/09/2025 06:24:04.426",
          "eventoId": "3000",
          "evento": "OBJETO Recebido",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "17/09/2025 08:54:05.828",
          "eventoId": "4100",
          "evento": "Entrega em andamento (na rua)",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "17/09/2025 09:45:26.243",
          "eventoId": "4250",
          "evento": "Entrega registrada via RT",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "17/09/2025 12:58:02.765",
          "eventoId": "4300",
          "evento": "Entrega registrada",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "17/09/2025 12:58:04.450",
          "eventoId": "5000",
          "evento": "Comprovante registrado",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        }
      ]
    },
    {
      "codigoCartao": "2510850138001",
      "meuNumero": "",
      "hawbId": "08070292536",
      "dtCol": "16/09/2025 15:00:15.000",
      "dtPost": "16/09/2025 15:00:22.941",
      "dtSla": "22/09/2025 23:59:59.000",
      "contrato": "14913",
      "baixa": [
        {
          "grauParentesco": "funcionário",
          "recebedor": "roselei bueno",
          "rg": "85639552",
          "dtBaixa": "17/09/2025 09:43:04.000",
          "tentativas": "1"
        }
      ],
      "historico": [
        {
          "ocorrencia": "15/09/2025 06:51:41.000",
          "eventoId": "1100",
          "evento": "Em arquivo-aguardando Postagem",
          "arCorreios": "",
          "frq": "SAO",
          "local": "Sao Bernardo do Campo"
        },
        {
          "ocorrencia": "16/09/2025 15:00:23.486",
          "eventoId": "1400",
          "evento": "Postado - logistica iniciada",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "16/09/2025 15:39:06.953",
          "eventoId": "2000",
          "evento": "Preparada para a transferencia",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "17/09/2025 06:24:03.523",
          "eventoId": "3000",
          "evento": "OBJETO Recebido",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "17/09/2025 08:54:05.828",
          "eventoId": "4100",
          "evento": "Entrega em andamento (na rua)",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "17/09/2025 09:45:25.996",
          "eventoId": "4250",
          "evento": "Entrega registrada via RT",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "17/09/2025 12:58:02.765",
          "eventoId": "4300",
          "evento": "Entrega registrada",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "17/09/2025 12:58:04.450",
          "eventoId": "5000",
          "evento": "Comprovante registrado",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        }
      ]
    },
    {
      "codigoCartao": "2510856139001",
      "meuNumero": "",
      "hawbId": "08071303045",
      "dtCol": "16/09/2025 15:00:15.000",
      "dtPost": "16/09/2025 15:00:20.490",
      "dtSla": "22/09/2025 23:59:59.000",
      "contrato": "14913",
      "baixa": [
        {
          "grauParentesco": "port",
          "recebedor": "Mayara  mira",
          "rg": "90717660206",
          "dtBaixa": "18/09/2025 11:24:29.000",
          "tentativas": "1"
        }
      ],
      "historico": [
        {
          "ocorrencia": "15/09/2025 11:03:58.000",
          "eventoId": "1100",
          "evento": "Em arquivo-aguardando Postagem",
          "arCorreios": "",
          "frq": "SAO",
          "local": "Sao Bernardo do Campo"
        },
        {
          "ocorrencia": "16/09/2025 15:00:21.069",
          "eventoId": "1400",
          "evento": "Postado - logistica iniciada",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "17/09/2025 14:57:19.868",
          "eventoId": "2000",
          "evento": "Preparada para a transferencia",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "18/09/2025 08:32:21.936",
          "eventoId": "3000",
          "evento": "OBJETO Recebido",
          "arCorreios": "",
          "frq": "JVL",
          "local": "Joinville"
        },
        {
          "ocorrencia": "18/09/2025 09:16:07.408",
          "eventoId": "4100",
          "evento": "Entrega em andamento (na rua)",
          "arCorreios": "",
          "frq": "JVL",
          "local": "Joinville"
        },
        {
          "ocorrencia": "18/09/2025 12:23:26.421",
          "eventoId": "4250",
          "evento": "Entrega registrada via RT",
          "arCorreios": "",
          "frq": "JVL",
          "local": "Joinville"
        },
        {
          "ocorrencia": "18/09/2025 17:40:02.881",
          "eventoId": "4300",
          "evento": "Entrega registrada",
          "arCorreios": "",
          "frq": "JVL",
          "local": "Joinville"
        },
        {
          "ocorrencia": "18/09/2025 17:40:04.687",
          "eventoId": "5000",
          "evento": "Comprovante registrado",
          "arCorreios": "",
          "frq": "JVL",
          "local": "Joinville"
        }
      ]
    },
    {
      "codigoCartao": "2510848140001",
      "meuNumero": "",
      "hawbId": "08075991969",
      "dtCol": "18/09/2025 08:29:59.000",
      "dtPost": "18/09/2025 08:30:38.400",
      "dtSla": "24/09/2025 23:59:59.000",
      "contrato": "14913",
      "baixa": [
        {
          "grauParentesco": "funcionário",
          "recebedor": "Allan  zapora",
          "rg": "13914866926",
          "dtBaixa": "19/09/2025 09:50:21.000",
          "tentativas": "1"
        }
      ],
      "historico": [
        {
          "ocorrencia": "16/09/2025 10:33:15.000",
          "eventoId": "1100",
          "evento": "Em arquivo-aguardando Postagem",
          "arCorreios": "",
          "frq": "SAO",
          "local": "Sao Bernardo do Campo"
        },
        {
          "ocorrencia": "18/09/2025 08:30:38.996",
          "eventoId": "1400",
          "evento": "Postado - logistica iniciada",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "18/09/2025 15:18:32.180",
          "eventoId": "2000",
          "evento": "Preparada para a transferencia",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "19/09/2025 07:32:02.447",
          "eventoId": "3000",
          "evento": "OBJETO Recebido",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "19/09/2025 08:56:51.863",
          "eventoId": "4100",
          "evento": "Entrega em andamento (na rua)",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "19/09/2025 09:53:25.450",
          "eventoId": "4250",
          "evento": "Entrega registrada via RT",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "19/09/2025 13:21:11.833",
          "eventoId": "4300",
          "evento": "Entrega registrada",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "19/09/2025 13:21:13.171",
          "eventoId": "5000",
          "evento": "Comprovante registrado",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        }
      ]
    },
    {
      "codigoCartao": "2510847141001",
      "meuNumero": "",
      "hawbId": "08079358946",
      "dtCol": "18/09/2025 14:37:41.000",
      "dtPost": "18/09/2025 14:39:09.148",
      "dtSla": "24/09/2025 23:59:59.000",
      "contrato": "14913",
      "baixa": [
        {
          "grauParentesco": "funcionário",
          "recebedor": "Allan  zapora",
          "rg": "13914866926",
          "dtBaixa": "19/09/2025 09:50:40.000",
          "tentativas": "1"
        }
      ],
      "historico": [
        {
          "ocorrencia": "17/09/2025 06:52:19.000",
          "eventoId": "1100",
          "evento": "Em arquivo-aguardando Postagem",
          "arCorreios": "",
          "frq": "SAO",
          "local": "Sao Bernardo do Campo"
        },
        {
          "ocorrencia": "18/09/2025 14:39:09.651",
          "eventoId": "1400",
          "evento": "Postado - logistica iniciada",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "18/09/2025 15:18:32.180",
          "eventoId": "2000",
          "evento": "Preparada para a transferencia",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "19/09/2025 07:37:43.875",
          "eventoId": "3000",
          "evento": "OBJETO Recebido",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "19/09/2025 08:56:51.863",
          "eventoId": "4100",
          "evento": "Entrega em andamento (na rua)",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "19/09/2025 09:53:25.431",
          "eventoId": "4250",
          "evento": "Entrega registrada via RT",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "19/09/2025 13:21:11.833",
          "eventoId": "4300",
          "evento": "Entrega registrada",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "19/09/2025 13:21:13.171",
          "eventoId": "5000",
          "evento": "Comprovante registrado",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        }
      ]
    },
    {
      "codigoCartao": "2510848142001",
      "meuNumero": "",
      "hawbId": "08080239007",
      "dtCol": "18/09/2025 14:37:41.000",
      "dtPost": "18/09/2025 14:39:10.759",
      "dtSla": "24/09/2025 23:59:59.000",
      "contrato": "14913",
      "baixa": [
        {
          "grauParentesco": "funcionário",
          "recebedor": "Allan  zapora",
          "rg": "13914866926",
          "dtBaixa": "19/09/2025 09:50:58.000",
          "tentativas": "1"
        }
      ],
      "historico": [
        {
          "ocorrencia": "17/09/2025 10:59:04.000",
          "eventoId": "1100",
          "evento": "Em arquivo-aguardando Postagem",
          "arCorreios": "",
          "frq": "SAO",
          "local": "Sao Bernardo do Campo"
        },
        {
          "ocorrencia": "18/09/2025 14:39:11.258",
          "eventoId": "1400",
          "evento": "Postado - logistica iniciada",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "18/09/2025 15:18:32.180",
          "eventoId": "2000",
          "evento": "Preparada para a transferencia",
          "arCorreios": "",
          "frq": "JAL",
          "local": "Colombo"
        },
        {
          "ocorrencia": "19/09/2025 07:37:42.783",
          "eventoId": "3000",
          "evento": "OBJETO Recebido",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "19/09/2025 08:56:51.863",
          "eventoId": "4100",
          "evento": "Entrega em andamento (na rua)",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "19/09/2025 09:53:25.558",
          "eventoId": "4250",
          "evento": "Entrega registrada via RT",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "19/09/2025 13:21:11.833",
          "eventoId": "4300",
          "evento": "Entrega registrada",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        },
        {
          "ocorrencia": "19/09/2025 13:21:13.171",
          "eventoId": "5000",
          "evento": "Comprovante registrado",
          "arCorreios": "",
          "frq": "ZUC",
          "local": "CURITIBA"
        }
      ]
    }
  ]
}
```
