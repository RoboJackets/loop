{{- range $key, $value := (key "loop/shared" | parseJSON) -}}
{{- $key | trimSpace -}}={{- $value | toJSON }}
{{ end -}}
{{- range service "mysql" }}
DB_SOCKET="{{- index .ServiceMeta "socket" | trimSpace -}}"
{{ end }}
{{- range service "meilisearch-v1-13" }}
MEILISEARCH_HOST="http://127.0.0.1:{{- .Port -}}"
{{ end }}
MEILISEARCH_KEY="{{- key "meilisearch/admin-key-v1.13" | trimSpace -}}"
{{- range service "tika" }}
TIKA_URL="http://127.0.0.1:{{- .Port -}}"
{{ end }}
{{ range $key, $value := (key (printf "loop/%s" (slice (env "NOMAD_JOB_NAME") 5)) | parseJSON) -}}
{{- $key | trimSpace -}}={{- $value | toJSON }}
{{ end -}}
APP_ENV="{{ slice (env "NOMAD_JOB_NAME") 5 }}"
APP_URL="https://{{- with (key "nginx/hostnames" | parseJSON) -}}{{- index . (env "NOMAD_JOB_NAME") -}}{{- end -}}"
CAS_CLIENT_SERVICE="https://{{- with (key "nginx/hostnames" | parseJSON) -}}{{- index . (env "NOMAD_JOB_NAME") -}}{{- end -}}"
CAS_VALIDATION="ca"
CAS_CERT="/etc/ssl/certs/USERTrust_RSA_Certification_Authority.pem"
HOME="/secrets/"
REDIS_CLIENT="phpredis"
REDIS_SCHEME="null"
REDIS_PORT="-1"
REDIS_HOST="/alloc/tmp/redis.sock"
REDIS_PASSWORD="{{ env "NOMAD_ALLOC_ID" }}"
REDIS_DB=0
REDIS_CACHE_DB=1
