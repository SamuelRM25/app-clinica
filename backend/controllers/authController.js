const User = require('../models/User');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');

exports.login = async (req, res) => {
  const { usuario, password } = req.body;

  try {
    console.log('Intento de login para usuario:', usuario);

    const user = await User.findOne({ where: { usuario } });
    if (!user) {
      console.log('Usuario no encontrado:', usuario);
      return res.status(401).json({ message: 'Usuario no encontrado' });
    }

    console.log('Usuario encontrado:', user.usuario);
    console.log('Contraseña almacenada (hash):', user.password);
    console.log('Contraseña proporcionada:', password);

    if (!password || !user.password) {
      console.error('La contraseña proporcionada o almacenada es undefined');
      return res.status(400).json({ message: 'Datos de contraseña inválidos' });
    }

    const isMatch = await bcrypt.compare(password, user.password);
    if (!isMatch) {
      console.log('Contraseña incorrecta para usuario:', usuario);
      return res.status(401).json({ message: 'Contraseña incorrecta' });
    }

    const token = jwt.sign({ userId: user.idUsuario }, 'secretkey', { expiresIn: '1h' });

    console.log('Login exitoso para usuario:', usuario);
    res.json({ token });
  } catch (err) {
    console.error('Error en el proceso de login:', err);
    res.status(500).json({ message: 'Error del servidor', error: err.message });
  }
};

// Método para registrar un nuevo usuario (solo para pruebas, no incluir en producción)
exports.register = async (req, res) => {
  const { usuario, contraseña, nombre, apellido, especialidad, telefono, email } = req.body;

  try {
    const hashedPassword = await bcrypt.hash(contraseña, 12);
    const newUser = await User.create({
      usuario,
      contraseña: hashedPassword,
      nombre,
      apellido,
      especialidad,
      telefono,
      email
    });

    res.status(201).json(newUser);
  } catch (err) {
    res.status(500).json({ message: 'Error del servidor', err });
  }
};
