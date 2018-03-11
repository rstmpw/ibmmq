# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.configure("2") do |config|
  config.vm.box = "CentOS7"
  config.vm.box_url = "http://files.rstm.pw/vagrant/CentOS7.json"

  config.vm.network "private_network", ip: "192.168.222.171"

  # Prev run on vagrant host:
  # vagrant plugin install vagrant-vbguest
  config.vm.synced_folder "./", "/vagrant", type: "virtualbox"

  config.vm.provider "virtualbox" do |vbox|
    vbox.name = "rstmpw-ibmmq"
  end

  # Install docker and create custom network
  config.vm.provision "shell", name: "Docker", inline: <<-SHELL
    bash <(curl -Ls https://raw.githubusercontent.com/rstmpw/docker/master/install.centos7.sh)
    sudo docker network create \
            --driver=bridge \
            --subnet=192.168.183.0/24 \
            --gateway=192.168.183.1 \
            custom-network-bridge
    sudo docker build -t php71cli ./
  SHELL


#  config.vm.provision "shell", run: "always", inline: <<-SHELL
#  	 php-cli.sh test/mqmh.php
#  SHELL
end