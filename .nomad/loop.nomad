variable "image" {
  type = string
  description = "The image to use for running the service"
}

variable "precompressed_assets" {
  type = bool
  description = "Whether assets in the image are pre-compressed"
}

variable "environment_name" {
  type = string
  description = "The name of the environment being deployed"
}

locals {
  # compressed in this context refers to the config string itself, not the assets
  compressed_nginx_configuration = trimspace(
    trimsuffix(
      trimspace(
        regex_replace(
          regex_replace(
            regex_replace(
              regex_replace(
                regex_replace(
                  regex_replace(
                    regex_replace(
                      regex_replace(
                        trimspace(
                          file("conf/nginx.conf")
                        ),
                        "server\\s{\\s",      # remove server keyword and opening bracket (autogenerated in nginx nomad job)
                        ""
                      ),
                      "server_name\\s\\S+;",  # remove server_name directive (autogenerated in nginx nomad job)
                      ""
                    ),
                    "root\\s\\S+;",           # remove root directive (autogenerated in nginx nomad job)
                    ""
                  ),
                  "listen\\s.+;",             # remove listen directive  (autogenerated in nginx nomad job)
                  ""
                ),
                "#.+\\n",                     # remove comments (no semantic difference)
                ""
              ),
              ";\\s+",                        # remove whitespace after semicolons (no semantic difference)
              ";"
            ),
            "{\\s+",                          # remove whitespace after opening brackets (no semantic difference)
            "{"
          ),
          "\\s+",                             # replace any occurrence of one or more whitespace characters with single space (no semantic difference)
          " "
        )
      ),
      "}"                                     # remove trailing closing bracket (autogenerated in nginx nomad job)
    )
  )

  # remove gzip_static directive if/when image does not contain compressed assets (handled at GitHub Actions/operator level)
  compressed_nginx_configuration_without_gzip_static = regex_replace(local.compressed_nginx_configuration,"gzip_static\\s\\S+;","")
}

job "loop" {
  region = "campus"

  datacenters = ["bcdc"]

  type = "service"

  group "loop" {
    volume "run" {
      type = "host"
      source = "run"
    }

    task "prestart" {
      driver = "docker"

      lifecycle {
        hook = "prestart"
      }

      config {
        image = var.image

        network_mode = "host"

        entrypoint = [
          "/bin/bash",
          "-xeuo",
          "pipefail",
          "-c",
          trimspace(file("scripts/prestart.sh"))
        ]

        mount {
          type = "volume"
          target = "/assets/"
          source = "assets"
          readonly = false

          volume_options {
            no_copy = true
          }
        }
      }

      resources {
        cpu = 100
        memory = 128
        memory_max = 2048
      }

      volume_mount {
        volume = "run"
        destination = "/var/opt/nomad/run/"
      }

      template {
        data = trimspace(file("conf/.env.tpl"))

        destination = "/secrets/.env"
        env = true
      }

      template {
        data = <<EOF
DOCKER_IMAGE_DIGEST="${split("@", var.image)[1]}"
EOF

        destination = "/secrets/.docker_image_digest"
        env = true
      }

      template {
        data = trimspace(file("conf/.my.cnf"))

        destination = "/secrets/.my.cnf"

        change_mode = "noop"
      }
    }

    task "web" {
      driver = "docker"

      config {
        image = var.image

        network_mode = "host"

        mount {
          type   = "bind"
          source = "local/"
          target = "/etc/php/8.3/fpm/pool.d/"
        }

        mount {
          type = "volume"
          target = "/app/storage/app/"
          source = "${NOMAD_JOB_NAME}"
          readonly = false

          volume_options {
            no_copy = false
          }
        }

        entrypoint = [
          "/bin/bash",
          "-xeuo",
          "pipefail",
          "-c",
          trimspace(file("scripts/web.sh"))
        ]
      }

      resources {
        cpu = 100
        memory = 256
        memory_max = 2048
      }

      volume_mount {
        volume = "run"
        destination = "/var/opt/nomad/run/"
      }

      template {
        data = trimspace(file("conf/www.conf"))

        destination = "local/www.conf"
      }

      template {
        data = trimspace(file("conf/.env.tpl"))

        destination = "/secrets/.env"
        env = true
      }

      template {
        data = "DOCKER_IMAGE_DIGEST=\"${split("@", var.image)[1]}\""

        destination = "/secrets/.docker_image_digest"
        env = true
      }

      template {
        data = trimspace(file("conf/.my.cnf"))

        destination = "/secrets/.my.cnf"

        change_mode = "noop"
      }

      service {
        name = "${NOMAD_JOB_NAME}"

        tags = [
          "fastcgi"
        ]

        check {
          name = "GET /ping"

          type = "script"

          command = "/bin/bash"

          args = [
            "-euxo",
            "pipefail",
            "-c",
            trimspace(file("scripts/healthcheck.sh"))
          ]

          interval = "5s"
          timeout = "5s"
        }

        check_restart {
          limit = 5
          grace = "20s"
        }

        meta {
          nginx-config = var.precompressed_assets ? local.compressed_nginx_configuration : local.compressed_nginx_configuration_without_gzip_static
          socket = "/var/opt/nomad/run/${NOMAD_JOB_NAME}-${NOMAD_ALLOC_ID}.sock"
          firewall-rules = jsonencode(["internet"])
          referrer-policy = "same-origin"
        }
      }

      restart {
        attempts = 1
        delay = "10s"
        interval = "1m"
        mode = "fail"
      }

      action "index-all-models" {
        command = "/usr/bin/php"

        args = [
          "-f",
          "/app/artisan",
          "scout:import-all",
        ]
      }

      action "generate-thumbnails" {
        command = "/usr/bin/php"

        args = [
          "-f",
          "/app/artisan",
          "generate:thumbnails",
        ]
      }

      shutdown_delay = var.environment_name == "production" ? "30s" : "0s"
    }


    dynamic "task" {
      for_each = ["scheduler", "worker"]

      labels = [task.value]

      content {
        driver = "docker"

        config {
          image = var.image

          network_mode = "host"

          entrypoint = [
            "/bin/bash",
            "-xeuo",
            "pipefail",
            "-c",
            trimspace(file("scripts/${task.value}.sh"))
          ]

          mount {
            type = "volume"
            target = "/assets/"
            source = "assets"
            readonly = false

            volume_options {
              no_copy = true
            }
          }

          mount {
            type = "volume"
            target = "/app/storage/app/"
            source = "${NOMAD_JOB_NAME}"
            readonly = false

            volume_options {
              no_copy = false
            }
          }
        }

        resources {
          cpu = 100
          memory = task.value == "worker" ? 512 : 128
          memory_max = 2048
        }

        volume_mount {
          volume = "run"
          destination = "/var/opt/nomad/run/"
        }

        template {
          data = trimspace(file("conf/.env.tpl"))

          destination = "/secrets/.env"
          env = true
        }

        template {
          data = "DOCKER_IMAGE_DIGEST=\"${split("@", var.image)[1]}\""

          destination = "/secrets/.docker_image_digest"
          env = true
        }

        template {
          data = trimspace(file("conf/.my.cnf"))

          destination = "/secrets/.my.cnf"

          change_mode = "noop"
        }
      }
    }
  }

  reschedule {
    delay = "10s"
    delay_function = "fibonacci"
    max_delay = "60s"
    unlimited = true
  }

  update {
    healthy_deadline = "5m"
    progress_deadline = "10m"
    auto_revert = true
    auto_promote = true
    canary = 1
  }
}
