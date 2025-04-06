# Proxmox Dashboard

A simple dashboard that auto-populates your VMs and LXC containers from your Proxmox instance into a simple user interface. Uses the Proxmox API to pull your machine data.

- View the running status of each machine.

- Easily access the web container, or SSH of each node at the click of a button.

- Just edit your Proxmox notes as follows:

    - IP: x.x.x.x Port: xxxx (SSH-Port: xxxx - optional)

# Installation

- Place this into a directory in your web server node (e.g: /var/www/html/proxdash/) and make sure it is accessible via HTTP.

- Edit config.php with your Proxmox IP, credentials and node name.

- You can then access the dashboard by navigating to `http://your-server-ip/dashboard.php`.


## Notes

Just a simple bookmark page for now to keep track of and access your machines.
