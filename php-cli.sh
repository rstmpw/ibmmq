sudo docker run --rm -it \
    --net custom-network-bridge \
    -v /vagrant:/vagrant \
    -w /vagrant \
    php71cli php -f "$@"