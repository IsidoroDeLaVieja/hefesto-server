FROM redis:latest

RUN apt update && apt upgrade -y

VOLUME /data

EXPOSE 6379

CMD ["redis-server"]
