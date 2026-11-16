# Note — Démarrer ma VM Debian (`debian-vm`) en CLI

VM VirtualBox : `debian-vm` (`f7eed200-f0d6-46f2-bec4-b82959fb8589`)

## 1. Diagnostic (une fois)

```bash
systemd-detect-virt
lscpu | grep -i virtual
lsmod | grep kvm
```

Résultat attendu sur ton hôte :

- `systemd-detect-virt` → `none` (machine nue, pas une VM dans une VM)
- `Virtualisation : VT-x`
- `kvm_intel`, `kvm`, `irqbypass` chargés → **conflit avec VirtualBox** (VT-x déjà pris)

Erreur typique si KVM est chargé :

```
VT-x is being used by another hypervisor (VERR_VMX_IN_VMX_ROOT_MODE)
```

## 2. Libérer VT-x puis démarrer

```bash
./virtualbox-activate.sh
VBoxManage startvm "debian-vm" --type headless
```

Succès attendu :

```
Waiting for VM "debian-vm" to power on...
VM "debian-vm" has been successfully started.
```

## 3. Vérifier que la VM tourne

```bash
VBoxManage list runningvms
VBoxManage showvminfo "debian-vm" --machinereadable | grep VMState
```

Attendu : `VMState="running"`

## 4. Entrer dans la VM (SSH)

Récupérer l’IP :

```bash
VBoxManage showvminfo "debian-vm" --machinereadable | grep -i -E "GuestIPAddress|IPAddress"
```

Puis :

```bash
ssh tonUtilisateur@IP_DE_LA_VM
```

Si réseau NAT (pas d’IP directe), ajoute un port-forward une fois :

```bash
VBoxManage modifyvm "debian-vm" --natpf1 "ssh,tcp,,2222,,22"
ssh -p 2222 tonUtilisateur@localhost
```

Si SSH refuse la connexion, ouvre la console graphique une fois (`VBoxManage startvm "debian-vm" --type gui`), connecte-toi dans Debian, puis :

```bash
sudo apt update
sudo apt install -y openssh-server
sudo systemctl enable --now ssh
ip a
```

## 5. Arrêter la VM proprement

```bash
VBoxManage controlvm "debian-vm" acpipowerbutton
# ou sauvegarde de l’état :
VBoxManage controlvm "debian-vm" savestate
```

## 6. Ensuite : tests MiniShop dans la VM

```bash
sudo apt update
sudo apt install -y php8.2-cli php8.2-mbstring php8.2-mysql php8.2-intl php8.2-xml php8.2-curl php8.2-zip unzip git python3 python3-pip nodejs npm mariadb-server

git clone https://github.com/jojo8356/SAE-302.git
cd SAE-302
git checkout arena/10afc76d-sae-302
composer install
npm install
pip install -r requirements-docs.txt

cp .env.example .env
./run.sh --db
php scripts/run-all-tests.php
```
